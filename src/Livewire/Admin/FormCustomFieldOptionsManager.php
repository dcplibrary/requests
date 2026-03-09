<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\CustomFieldOption;
use Dcplibrary\Sfp\Models\FormCustomField;
use Dcplibrary\Sfp\Models\FormCustomFieldOption;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

/**
 * Per-form option manager for select/radio CustomFields.
 *
 * Displays a table of options drawn from sfp_custom_field_options and merged
 * with any per-form overrides in form_custom_field_options.  Provides:
 *   - Visible toggle  (per-form; does not affect the global active state)
 *   - Up / down reordering (stored in sort_order on form_custom_field_options)
 *   - Edit link  → dedicated per-option editor page
 *
 * Embedded inside FormCustomFieldEdit when field type is select or radio.
 */
class FormCustomFieldOptionsManager extends Component
{
    public int    $pivotId;
    public int    $fieldId;
    public int    $formId  = 0;
    public string $formSlug = '';

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public function mount(int $pivotId, int $fieldId, string $formSlug): void
    {
        $this->pivotId  = $pivotId;
        $this->fieldId  = $fieldId;
        $this->formSlug = $formSlug;

        $pivot = FormCustomField::find($pivotId);
        $this->formId = $pivot ? $pivot->form_id : 0;

        $this->loadItems();
    }

    // ── Public actions ────────────────────────────────────────────────────────

    public function toggleVisible(int $optionId): void
    {
        $current = collect($this->items)->firstWhere('id', $optionId);
        if (! $current) {
            return;
        }

        FormCustomFieldOption::updateOrInsert(
            ['form_id' => $this->formId, 'custom_field_option_id' => $optionId],
            ['visible' => ! $current['visible']]
        );

        $this->loadItems();
    }

    public function moveUp(int $index): void
    {
        if ($index <= 0) {
            return;
        }
        [$this->items[$index - 1], $this->items[$index]] = [$this->items[$index], $this->items[$index - 1]];
        $this->persistOrder();
        $this->loadItems();
    }

    public function moveDown(int $index): void
    {
        if ($index >= count($this->items) - 1) {
            return;
        }
        [$this->items[$index + 1], $this->items[$index]] = [$this->items[$index], $this->items[$index + 1]];
        $this->persistOrder();
        $this->loadItems();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function loadItems(): void
    {
        if ($this->formId === 0) {
            $this->items = [];
            return;
        }

        $baseOptions = CustomFieldOption::where('custom_field_id', $this->fieldId)
            ->orderBy('sort_order')
            ->get();

        $optionIds = $baseOptions->pluck('id')->all();

        $overrides = FormCustomFieldOption::where('form_id', $this->formId)
            ->whereIn('custom_field_option_id', $optionIds)
            ->get()
            ->keyBy('custom_field_option_id');

        $items = $baseOptions->map(function (CustomFieldOption $opt, int $modelIndex) use ($overrides) {
            $override = $overrides->get($opt->id);
            return [
                'id'             => $opt->id,
                'name'           => $opt->name,
                'slug'           => $opt->slug,
                'globally_active'=> (bool) $opt->active,
                'visible'        => $override ? (bool) $override->visible : true,
                'label_override' => (string) ($override?->label_override ?? ''),
                // Use override sort_order when present; else fall back to model position
                // (scaled so any override sorts before any unoverridden default).
                'sort_order'     => $override ? $override->sort_order : ($modelIndex + 1) * 10000,
            ];
        });

        $this->items = $items->sortBy('sort_order')->values()->all();
    }

    /**
     * Persist the current in-memory order to form_custom_field_options.
     */
    private function persistOrder(): void
    {
        foreach ($this->items as $i => $item) {
            FormCustomFieldOption::updateOrInsert(
                ['form_id' => $this->formId, 'custom_field_option_id' => $item['id']],
                ['sort_order' => $i + 1]
            );
        }
    }

    /** Build the URL for the per-option editor page. */
    public function editUrl(int $optionId): string
    {
        $prefix = trim(config('sfp.route_prefix', 'request'), '/');
        return URL::to('/' . $prefix . '/staff/settings/custom-fields/' . $this->fieldId
            . '/form/' . $this->formSlug . '/options/' . $optionId . '/edit');
    }

    public function render()
    {
        return view('sfp::livewire.admin.form-custom-field-options-manager');
    }
}

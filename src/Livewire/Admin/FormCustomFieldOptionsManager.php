<?php

namespace Dcplibrary\Requests\Livewire\Admin;

use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Dcplibrary\Requests\Models\FormFieldOptionOverride;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

/**
 * Per-form option manager for select/radio CustomFields.
 *
 * Displays a table of options drawn from custom_field_options and merged
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

        $pivot = FormFieldConfig::find($pivotId);
        $this->formId = $pivot ? $pivot->form_id : 0;

        $this->loadItems();
    }

    // ── Public actions ────────────────────────────────────────────────────────

    /**
     * Toggle per-form visibility for an option.
     *
     * @param  int  $optionId
     * @return void
     */
    public function toggleVisible(int $optionId): void
    {
        $opt     = FieldOption::find($optionId);
        $current = collect($this->items)->firstWhere('id', $optionId);
        if (! $current || ! $opt) {
            return;
        }

        FormFieldOptionOverride::upsertForForm(
            $this->formId,
            $this->fieldId,
            $opt->slug,
            ['visible' => ! $current['visible']]
        );

        $this->loadItems();
    }

    public function moveUp(int $index): void
    {
        if ($index <= 0) {
            return;
        }
        $this->items = array_values($this->items);
        [$this->items[$index - 1], $this->items[$index]] = [$this->items[$index], $this->items[$index - 1]];
        $this->items = array_values($this->items);
        $this->persistOrder();
    }

    public function moveDown(int $index): void
    {
        if ($index >= count($this->items) - 1) {
            return;
        }
        $this->items = array_values($this->items);
        [$this->items[$index + 1], $this->items[$index]] = [$this->items[$index], $this->items[$index + 1]];
        $this->items = array_values($this->items);
        $this->persistOrder();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Load options from field_options merged with per-form overrides.
     *
     * @return void
     */
    private function loadItems(): void
    {
        if ($this->formId === 0) {
            $this->items = [];
            return;
        }

        $baseOptions = FieldOption::where('field_id', $this->fieldId)
            ->ordered()
            ->get();

        $overrides = FormFieldOptionOverride::where('form_id', $this->formId)
            ->where('field_id', $this->fieldId)
            ->get()
            ->keyBy('option_slug');

        $items = $baseOptions->map(function (FieldOption $opt, int $modelIndex) use ($overrides) {
            $override = $overrides->get($opt->slug);

            return [
                'id'              => $opt->id,
                'slug'            => $opt->slug,
                'name'            => $opt->name,
                'globally_active' => (bool) $opt->active,
                'visible'         => $override ? (bool) $override->visible : true,
                'label_override'  => (string) ($override?->label_override ?? ''),
                'sort_order'      => $override ? $override->sort_order : ($modelIndex + 1) * 10000,
            ];
        });

        $this->items = $items->sortBy('sort_order')->values()->all();
    }

    /**
     * Persist the current in-memory order to form_custom_field_options.
     */
    /**
     * Persist the current in-memory order to form_field_option_overrides.
     *
     * @return void
     */
    private function persistOrder(): void
    {
        $rows = array_values($this->items);
        foreach ($rows as $i => $item) {
            $order = $i + 1;
            FormFieldOptionOverride::upsertForForm(
                $this->formId,
                $this->fieldId,
                $item['slug'],
                ['sort_order' => $order]
            );
            $this->items[$i]['sort_order'] = $order;
        }
    }

    /** Build the URL for the per-option editor page. */
    public function editUrl(int $optionId): string
    {
        $prefix = trim(config('requests.route_prefix', 'request'), '/');
        return URL::to('/' . $prefix . '/staff/settings/custom-fields/' . $this->fieldId
            . '/form/' . $this->formSlug . '/options/' . $optionId . '/edit');
    }

    public function render()
    {
        return view('requests::livewire.admin.form-custom-field-options-manager');
    }
}

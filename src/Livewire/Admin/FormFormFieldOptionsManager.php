<?php

namespace Dcplibrary\Requests\Livewire\Admin;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\FormFieldOptionOverride;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

/**
 * Per-form option manager for option-type FormFields (material_type, audience, genre, console).
 *
 * Displays a table of options — drawn from the underlying model — merged with any
 * per-form overrides stored in form_form_field_options.  Provides:
 *   - Visible toggle  (per-form; does not affect the global active state)
 *   - Up / down reordering (stored in sort_order on form_form_field_options)
 *   - Edit link  → dedicated per-option editor page
 *
 * Embedded inside FormFormFieldEdit whenever the field key maps to an option model.
 */
class FormFormFieldOptionsManager extends Component
{
    public int $formId;
    public int $fieldId;
    public string $fieldKey  = '';
    public string $formSlug  = '';

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    /** Keys that map to an option model. */
    public const OPTION_KEYS = ['material_type', 'audience', 'genre', 'console'];

    public function mount(int $formId, int $fieldId, string $fieldKey, string $formSlug): void
    {
        $this->formId   = $formId;
        $this->fieldId  = $fieldId;
        $this->fieldKey = $fieldKey;
        $this->formSlug = $formSlug;
        $this->loadItems();
    }

    // ── Public actions ────────────────────────────────────────────────────────

    /**
     * Toggle per-form visibility for an option slug.
     *
     * @param  string  $slug
     * @return void
     */
    public function toggleVisible(string $slug): void
    {
        $current = collect($this->items)->firstWhere('slug', $slug);
        if (! $current) {
            return;
        }

        FormFieldOptionOverride::updateOrInsert(
            ['form_id' => $this->formId, 'field_id' => $this->fieldId, 'option_slug' => $slug],
            ['visible' => ! $current['visible'], 'sort_order' => $current['sort_order']]
        );

        $this->loadItems();
    }

    /**
     * Move an option up by its slug.
     *
     * @param  string  $slug
     * @return void
     */
    public function moveUp(string $slug): void
    {
        $this->items = array_values($this->items);
        $index = collect($this->items)->search(fn ($item) => $item['slug'] === $slug);
        if ($index === false || $index <= 0) {
            return;
        }
        [$this->items[$index - 1], $this->items[$index]] = [$this->items[$index], $this->items[$index - 1]];
        $this->items = array_values($this->items);
        $this->persistOrder();
    }

    /**
     * Move an option down by its slug.
     *
     * @param  string  $slug
     * @return void
     */
    public function moveDown(string $slug): void
    {
        $this->items = array_values($this->items);
        $index = collect($this->items)->search(fn ($item) => $item['slug'] === $slug);
        if ($index === false || $index >= count($this->items) - 1) {
            return;
        }
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
                'slug'           => $opt->slug,
                'name'           => $opt->name,
                'globally_active' => (bool) $opt->active,
                'visible'        => $override ? (bool) $override->visible : true,
                'label_override' => (string) ($override?->label_override ?? ''),
                'sort_order'     => $override ? $override->sort_order : ($modelIndex + 1) * 10000,
            ];
        });

        $this->items = $items->sortBy('sort_order')->values()->all();
    }

    /**
     * Persist the current in-memory order to form_field_option_overrides.
     *
     * Uses firstOrNew so that newly created rows default visible to true
     * rather than leaving it null (which loadItems would interpret as hidden).
     *
     * @return void
     */
    private function persistOrder(): void
    {
        $rows = array_values($this->items);
        foreach ($rows as $i => $item) {
            $order = $i + 1;
            $override = FormFieldOptionOverride::firstOrNew([
                'form_id'     => $this->formId,
                'field_id'    => $this->fieldId,
                'option_slug' => $item['slug'],
            ]);
            if (! $override->exists) {
                $override->visible = true;
            }
            $override->sort_order = $order;
            $override->save();
            $this->items[$i]['sort_order'] = $order;
        }
    }

    /** Build the URL for the per-option editor page. */
    public function editUrl(string $slug): string
    {
        $prefix = trim(config('requests.route_prefix', 'request'), '/');
        return URL::to('/' . $prefix . '/staff/settings/form-fields/' . $this->fieldId
            . '/form/' . $this->formSlug . '/options/' . $slug . '/edit');
    }

    public function render()
    {
        return view('requests::livewire.admin.form-form-field-options-manager');
    }
}

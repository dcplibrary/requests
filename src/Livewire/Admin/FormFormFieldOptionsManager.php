<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\Console;
use Dcplibrary\Sfp\Models\FormFormFieldOption;
use Dcplibrary\Sfp\Models\Genre;
use Dcplibrary\Sfp\Models\MaterialType;
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

    public function toggleVisible(string $slug): void
    {
        $current = collect($this->items)->firstWhere('slug', $slug);
        if (! $current) {
            return;
        }

        FormFormFieldOption::updateOrInsert(
            ['form_id' => $this->formId, 'form_field_id' => $this->fieldId, 'option_slug' => $slug],
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
        $modelClass = $this->modelClassForKey($this->fieldKey);
        if (! $modelClass) {
            $this->items = [];
            return;
        }

        $baseOptions = $modelClass::orderBy('sort_order')->get();

        $overrides = FormFormFieldOption::where('form_id', $this->formId)
            ->where('form_field_id', $this->fieldId)
            ->get()
            ->keyBy('option_slug');

        $items = $baseOptions->map(function ($opt, int $modelIndex) use ($overrides) {
            $override = $overrides->get($opt->slug);
            return [
                'slug'           => $opt->slug,
                'name'           => $opt->name,
                'globally_active'=> (bool) $opt->active,
                'visible'        => $override ? (bool) $override->visible : true,
                'label_override' => (string) ($override?->label_override ?? ''),
                // Use override sort_order when available; otherwise fall back to model position (scaled
                // so any override < any unoverridden default when sorted together).
                'sort_order'     => $override ? $override->sort_order : ($modelIndex + 1) * 10000,
            ];
        });

        $this->items = $items->sortBy('sort_order')->values()->all();
    }

    /**
     * Persist the current in-memory order to form_form_field_options.
     * Writes (or updates) every row so the display order is fully captured.
     */
    private function persistOrder(): void
    {
        foreach ($this->items as $i => $item) {
            FormFormFieldOption::updateOrInsert(
                ['form_id' => $this->formId, 'form_field_id' => $this->fieldId, 'option_slug' => $item['slug']],
                ['sort_order' => $i + 1]
            );
        }
    }

    /** Map a FormField key to its underlying option model class. */
    public static function modelClassForKey(string $key): ?string
    {
        return match ($key) {
            'material_type' => MaterialType::class,
            'audience'      => Audience::class,
            'genre'         => Genre::class,
            'console'       => Console::class,
            default         => null,
        };
    }

    /** Build the URL for the per-option editor page. */
    public function editUrl(string $slug): string
    {
        $prefix = trim(config('sfp.route_prefix', 'request'), '/');
        return URL::to('/' . $prefix . '/staff/settings/form-fields/' . $this->fieldId
            . '/form/' . $this->formSlug . '/options/' . $slug . '/edit');
    }

    public function render()
    {
        return view('sfp::livewire.admin.form-form-field-options-manager');
    }
}

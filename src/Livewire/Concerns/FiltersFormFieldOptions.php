<?php

namespace Dcplibrary\Requests\Livewire\Concerns;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\FormFieldOptionOverride;
use Illuminate\Support\Collection;

/**
 * Shared logic for loading field options with per-form visibility,
 * sort order, and label overrides applied.
 *
 * Used by both the SFP and ILL patron-facing form components.
 */
trait FiltersFormFieldOptions
{
    /**
     * Load options for a field, filtered by per-form visibility, sort order, and label overrides.
     *
     * Returns FieldOption model instances so callers can still access ->meta() and other
     * model methods (e.g. has_other_text on material types).
     *
     * When no formId is provided (or no overrides exist), the base active/ordered options
     * are returned unchanged.
     *
     * @param  int       $fieldId
     * @param  int|null  $formId
     * @return Collection<int, FieldOption>
     */
    private function formFilteredOptions(int $fieldId, ?int $formId): Collection
    {
        $options = FieldOption::where('field_id', $fieldId)->active()->ordered()->get();

        if (! $formId) {
            return $options;
        }

        $overrides = FormFieldOptionOverride::where('form_id', $formId)
            ->where('field_id', $fieldId)
            ->get()
            ->keyBy('option_slug');

        return $options
            ->filter(function (FieldOption $opt) use ($overrides) {
                $ov = $overrides->get($opt->slug);
                return $ov ? (bool) $ov->visible : true;
            })
            ->sortBy(function (FieldOption $opt, int $index) use ($overrides) {
                $ov = $overrides->get($opt->slug);
                return $ov && $ov->sort_order ? $ov->sort_order : ($index + 1) * 10000;
            })
            ->each(function (FieldOption $opt) use ($overrides) {
                $ov = $overrides->get($opt->slug);
                if ($ov && $ov->label_override !== null && $ov->label_override !== '') {
                    $opt->name = $ov->label_override;
                }
            })
            ->values();
    }

    /**
     * Convenience: return options as a [slug => name] map with per-form overrides applied.
     *
     * @param  int       $fieldId
     * @param  int|null  $formId
     * @return array<string, string>
     */
    private function formFilteredOptionMap(int $fieldId, ?int $formId): array
    {
        return $this->formFilteredOptions($fieldId, $formId)
            ->pluck('name', 'slug')
            ->all();
    }

    /**
     * Build a select/radio validation rule from the allowed slugs for a field.
     *
     * @param  Field     $field
     * @param  bool      $required
     * @param  int|null  $formId
     * @return string
     */
    private function selectOrRadioRule(Field $field, bool $required, ?int $formId): string
    {
        $slugs = $this->formFilteredOptions($field->id, $formId)
            ->pluck('slug')
            ->all();
        $base = $required ? 'required' : 'nullable';

        return $slugs !== [] ? $base . '|in:' . implode(',', $slugs) : $base;
    }
}

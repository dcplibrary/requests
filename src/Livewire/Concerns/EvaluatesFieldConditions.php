<?php

namespace Dcplibrary\Requests\Livewire\Concerns;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;

/**
 * Universal conditional-field evaluation for patron-facing forms.
 *
 * Expects the consuming component to declare:
 *   public ?int   $material_type_id
 *   public array  $custom           (key => value map of field answers)
 *
 * Optionally:
 *   public ?int   $audience_id      (SFP has a dedicated property)
 *   public string $genre            (SFP has a dedicated property)
 *   public string $console          (SFP has a dedicated property)
 */
trait EvaluatesFieldConditions
{
    /**
     * Build the current form state used to evaluate conditional logic.
     *
     * Includes material_type slug, audience slug (if the component has
     * a dedicated $audience_id property), and all select/radio values
     * from $this->custom.  Also includes any dedicated string properties
     * that correspond to known field keys (genre, console, etc.).
     *
     * @return array<string, string|null>
     */
    protected function formConditionState(): array
    {
        $state = [
            'material_type' => $this->material_type_id
                ? (FieldOption::find($this->material_type_id)?->slug ?? '')
                : '',
        ];

        // SFP stores audience in a dedicated property
        if (property_exists($this, 'audience_id') && $this->audience_id !== null) {
            $state['audience'] = FieldOption::find($this->audience_id)?->slug ?? '';
        }

        // Include dedicated string properties for core field keys
        foreach (['genre', 'console'] as $key) {
            if (property_exists($this, $key) && is_string($this->{$key}) && $this->{$key} !== '') {
                $state[$key] = $this->{$key};
            }
        }

        // Include all select/radio custom values (ILL stores everything here)
        foreach ($this->custom as $key => $val) {
            if (is_string($val) && $val !== '' && ! isset($state[$key])) {
                $state[$key] = $val;
            }
        }

        return $state;
    }

    /**
     * Evaluate conditions for a collection of fields and return a visibility map.
     *
     * Accepts Field models or stdClass wrappers (ILL uses the latter).
     * Each item must have a ->key and either ->condition or be a Field
     * model with isVisibleFor().
     *
     * @param  iterable  $fields
     * @return array<string, bool>
     */
    protected function buildVisibilityMap(iterable $fields): array
    {
        $state = $this->formConditionState();
        $map   = [];

        foreach ($fields as $f) {
            if ($f instanceof Field) {
                $map[$f->key] = $f->isVisibleFor($state);
            } else {
                $condition = $f->condition ?? ['match' => 'all', 'rules' => []];
                $map[$f->key] = Field::evaluateCondition($condition, $state);
            }
        }

        return $map;
    }

    /**
     * Check whether a single field key is visible in a pre-built visibility map.
     *
     * @param  string               $key
     * @param  array<string, bool>  $visibilityMap
     * @return bool
     */
    protected function isFieldVisible(string $key, array $visibilityMap): bool
    {
        return $visibilityMap[$key] ?? false;
    }
}

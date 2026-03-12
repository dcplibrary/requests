<?php

namespace Dcplibrary\Requests\Livewire\Admin;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Dcplibrary\Requests\Models\FormFieldOptionOverride;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * Edit a single FormField — label, required, active, and conditional logic.
 * Mounted from the form-fields edit page via @livewire('requests-admin-form-field-edit').
 */
class FormFieldEdit extends Component
{
    public int $fieldId;

    public string $label    = '';
    public string $type     = 'text';
    public bool   $required = false;
    public bool   $active   = false;
    public bool   $includeAsToken = false;
    public bool   $filterable = false;

    /** @var array{match: string, rules: array<int, array<string, mixed>>} */
    public array $condition    = ['match' => 'all', 'rules' => []];
    public bool  $hasCondition = false;

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(int $fieldId): void
    {
        $field = Field::findOrFail($fieldId);

        $this->fieldId        = $fieldId;
        $this->label          = $field->label;
        $this->type           = $field->type ?? 'text';
        $this->required       = (bool) $field->required;
        $this->active         = (bool) $field->active;
        $this->includeAsToken = (bool) $field->include_as_token;
        $this->filterable     = (bool) $field->filterable;
        $this->condition      = $field->condition ?? ['match' => 'all', 'rules' => []];
        $this->hasCondition   = ! empty($this->condition['rules']);
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    /** @var array<string, string> Valid field types for the dropdown. */
    public const FIELD_TYPES = [
        'text'     => 'Text',
        'textarea' => 'Textarea',
        'select'   => 'Select (Dropdown)',
        'radio'    => 'Radio',
        'checkbox' => 'Checkbox',
        'date'     => 'Date',
        'number'   => 'Number',
        'html'     => 'Rich Text (HTML)',
    ];

    /**
     * Persist field changes.
     *
     * @return void
     */
    public function save(): void
    {
        $this->label = trim($this->label);

        $this->validate([
            'label' => 'required|string|max:100',
            'type'  => 'required|in:' . implode(',', array_keys(self::FIELD_TYPES)),
        ]);

        $condition = ($this->hasCondition && ! empty($this->condition['rules']))
            ? $this->condition
            : null;

        Field::where('id', $this->fieldId)->update([
            'label'            => $this->label,
            'type'             => $this->type,
            'required'         => $this->required,
            'active'           => $this->active,
            'include_as_token' => $this->includeAsToken,
            'filterable'       => $this->filterable,
            'condition'        => $condition ? json_encode($condition) : null,
        ]);

        session()->flash('success', "'{$this->label}' updated.");
        $this->redirect(route('request.staff.settings.form-fields'));
    }

    /**
     * Soft-delete the field and cascade-remove related config rows.
     *
     * @return void
     */
    public function deleteField(): void
    {
        $field = Field::findOrFail($this->fieldId);
        $label = $field->label;

        DB::transaction(function () use ($field): void {
            // Remove per-form config rows (hard delete — no SoftDeletes)
            FormFieldConfig::where('field_id', $field->id)->delete();

            // Remove per-form option overrides (hard delete)
            FormFieldOptionOverride::where('field_id', $field->id)->delete();

            // Remove selector group pivots
            $optionIds = $field->options()->pluck('id')->all();
            if ($optionIds) {
                DB::table('selector_group_field_option')
                    ->whereIn('field_option_id', $optionIds)
                    ->delete();
            }

            // Soft-delete options
            $field->options()->delete();

            // Soft-delete the field itself
            $field->delete();
        });

        session()->flash('success', "'{$label}' has been deleted.");
        $this->redirect(route('request.staff.settings.form-fields'));
    }

    // ── Conditional logic ─────────────────────────────────────────────────────

    public function toggleHasCondition(): void
    {
        $this->hasCondition = ! $this->hasCondition;

        if ($this->hasCondition && empty($this->condition['rules'])) {
            $this->condition = ['match' => 'all', 'rules' => []];
        }
    }

    public function setConditionMatch(string $match): void
    {
        $this->condition['match'] = $match;
    }

    public function addRule(): void
    {
        $this->condition['rules'][] = [
            'field'    => 'material_type',
            'operator' => 'in',
            'values'   => [],
        ];
    }

    public function removeRule(int $ruleIndex): void
    {
        array_splice($this->condition['rules'], $ruleIndex, 1);
    }

    public function setRuleField(int $ruleIndex, string $field): void
    {
        $this->condition['rules'][$ruleIndex]['field']  = $field;
        $this->condition['rules'][$ruleIndex]['values'] = [];
    }

    public function setRuleOperator(int $ruleIndex, string $operator): void
    {
        $this->condition['rules'][$ruleIndex]['operator'] = $operator;
    }

    public function toggleRuleValue(int $ruleIndex, string $value): void
    {
        $values = $this->condition['rules'][$ruleIndex]['values'] ?? [];

        if (in_array($value, $values, true)) {
            $values = array_values(array_filter($values, fn ($v) => $v !== $value));
        } else {
            $values[] = $value;
        }

        $this->condition['rules'][$ruleIndex]['values'] = $values;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        /** @var Field $field */
        $field = Field::findOrFail($this->fieldId);

        // Condition field keys for rule value pickers.
        $mtField  = Field::where('key', 'material_type')->first();
        $audField = Field::where('key', 'audience')->first();

        return view('requests::livewire.admin.form-field-edit', [
            'fieldKey'            => $field->key,
            'fieldTypes'          => self::FIELD_TYPES,
            'showOptionsManager'  => in_array($field->type, ['select', 'radio'], true),
            'materialTypeOptions' => $mtField
                ? FieldOption::where('field_id', $mtField->id)->active()->ordered()->pluck('name', 'slug')
                : collect(),
            'audienceOptions'     => $audField
                ? FieldOption::where('field_id', $audField->id)->active()->ordered()->pluck('name', 'slug')
                : collect(),
        ]);
    }
}

<?php

namespace Dcplibrary\Requests\Livewire\Admin;

use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Livewire\Component;

/**
 * Edit a custom field's per-form configuration (SFP or ILL).
 *
 * Edits the form_custom_fields pivot: label override, required, visible,
 * and conditional_logic.  The base field definition (key, type, etc.) is
 * unchanged; for that use the global custom field edit page.
 *
 * For select/radio fields the component also embeds FormCustomFieldOptionsManager
 * to let admins configure per-form option visibility and order.
 */
class FormCustomFieldEdit extends Component
{
    public int    $pivotId;
    public int    $fieldId;
    public string $formSlug    = '';
    public string $fieldType   = '';
    public bool   $hasOptions  = false;

    public string $labelOverride = '';
    public bool   $required      = false;
    public bool   $visible       = true;

    /** @var array{match: string, rules: array<int, array<string, mixed>>} */
    public array $condition    = ['match' => 'all', 'rules' => []];
    public bool  $hasCondition = false;

    public function mount(int $pivotId, int $fieldId, string $formSlug): void
    {
        $this->pivotId  = $pivotId;
        $this->fieldId  = $fieldId;
        $this->formSlug = $formSlug;

        $pivot = FormFieldConfig::findOrFail($pivotId);
        $this->labelOverride = (string) ($pivot->label_override ?? '');
        $this->required      = (bool) $pivot->required;
        $this->visible       = (bool) $pivot->visible;

        $field = Field::find($fieldId);

        // Load per-form conditional logic; fall back to the base field's condition so the
        // admin sees the effective condition rather than a blank state when none has been
        // explicitly overridden on the pivot yet.
        $this->condition    = $pivot->conditional_logic ?? ($field?->condition ?? ['match' => 'all', 'rules' => []]);
        $this->hasCondition = ! empty($this->condition['rules']);

        if ($field) {
            $this->fieldType  = $field->type;
            $this->hasOptions = in_array($field->type, ['select', 'radio'], true);
        }
    }

    public function save(): void
    {
        $conditionalLogic = ($this->hasCondition && ! empty($this->condition['rules']))
            ? $this->condition
            : null;

        $labelOverride = trim($this->labelOverride);
        FormFieldConfig::where('id', $this->pivotId)->update([
            'label_override'    => $labelOverride !== '' ? $labelOverride : null,
            'required'          => $this->required,
            'visible'           => $this->visible,
            'conditional_logic' => $conditionalLogic,
        ]);

        session()->flash('success', 'Settings for this form updated.');
        $this->redirect(route('request.staff.settings.form-fields', ['tab' => $this->formSlug]));
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

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        $mtField  = Field::where('key', 'material_type')->first();
        $audField = Field::where('key', 'audience')->first();

        return view('requests::livewire.admin.form-custom-field-edit', [
            'materialTypeOptions' => $mtField
                ? FieldOption::where('field_id', $mtField->id)->active()->ordered()->pluck('name', 'slug')
                : collect(),
            'audienceOptions'     => $audField
                ? FieldOption::where('field_id', $audField->id)->active()->ordered()->pluck('name', 'slug')
                : collect(),
        ]);
    }
}

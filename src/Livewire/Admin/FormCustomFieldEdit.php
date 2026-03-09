<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\FormCustomField;
use Dcplibrary\Sfp\Models\MaterialType;
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

        $pivot = FormCustomField::findOrFail($pivotId);
        $this->labelOverride = (string) ($pivot->label_override ?? '');
        $this->required      = (bool) $pivot->required;
        $this->visible       = (bool) $pivot->visible;
        $this->condition     = $pivot->conditional_logic ?? ['match' => 'all', 'rules' => []];
        $this->hasCondition  = ! empty($this->condition['rules']);

        $field = CustomField::find($fieldId);
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
        FormCustomField::where('id', $this->pivotId)->update([
            'label_override'    => $labelOverride !== '' ? $labelOverride : null,
            'required'          => $this->required,
            'visible'           => $this->visible,
            'conditional_logic' => $conditionalLogic ? json_encode($conditionalLogic) : null,
        ]);

        session()->flash('success', 'Settings for this form updated.');
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

    public function render()
    {
        return view('sfp::livewire.admin.form-custom-field-edit', [
            'materialTypeOptions' => MaterialType::orderBy('sort_order')->pluck('name', 'slug'),
            'audienceOptions'     => Audience::orderBy('sort_order')->pluck('name', 'slug'),
        ]);
    }
}

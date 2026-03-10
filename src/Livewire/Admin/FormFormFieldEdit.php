<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\Form;
use Dcplibrary\Sfp\Models\FormField;
use Dcplibrary\Sfp\Models\FormFormField;
use Dcplibrary\Sfp\Models\MaterialType;
use Livewire\Component;

/**
 * Edit a form field’s configuration for one form only (SFP or ILL).
 * Edits the pivot: required, visible, conditional_logic. Base field is not changed.
 */
class FormFormFieldEdit extends Component
{
    public int $pivotId;
    public int $fieldId;
    public string $formSlug  = '';
    public string $fieldKey  = '';
    public bool   $hasOptions = false;
    public int    $formId    = 0;

    public string $labelOverride = '';
    public bool $required = false;
    public bool $visible = true;

    /** @var array{match: string, rules: array<int, array<string, mixed>>} */
    public array $condition = ['match' => 'all', 'rules' => []];
    public bool $hasCondition = false;

    public function mount(int $pivotId, int $fieldId, string $formSlug): void
    {
        $this->pivotId  = $pivotId;
        $this->fieldId  = $fieldId;
        $this->formSlug = $formSlug;

        $pivot = FormFormField::findOrFail($pivotId);
        $this->labelOverride = (string) ($pivot->label_override ?? '');
        $this->required      = (bool) $pivot->required;
        $this->visible       = (bool) $pivot->visible;

        // Determine if this field has options (material_type, audience, genre, console).
        $field = FormField::find($fieldId);

        // Load per-form conditional logic; fall back to the base field's condition so the
        // admin sees the effective condition rather than a blank state when none has been
        // explicitly overridden on the pivot yet.
        $this->condition    = $pivot->conditional_logic ?? ($field?->condition ?? ['match' => 'all', 'rules' => []]);
        $this->hasCondition = ! empty($this->condition['rules']);
        if ($field) {
            $this->fieldKey  = $field->key;
            $this->hasOptions = in_array($field->key, FormFormFieldOptionsManager::OPTION_KEYS, true);
        }

        // Resolve the Form model ID needed by the options manager.
        $formModel    = Form::bySlug($formSlug);
        $this->formId = $formModel?->id ?? 0;
    }

    public function save(): void
    {
        $conditionalLogic = ($this->hasCondition && ! empty($this->condition['rules']))
            ? $this->condition
            : null;

        $labelOverride = trim($this->labelOverride);
        FormFormField::where('id', $this->pivotId)->update([
            'label_override'     => $labelOverride !== '' ? $labelOverride : null,
            'required'          => $this->required,
            'visible'           => $this->visible,
            'conditional_logic' => $conditionalLogic,
        ]);

        session()->flash('success', 'Settings for this form updated.');
        $this->redirect(route('request.staff.settings.form-fields', ['tab' => $this->formSlug]));
    }

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
        return view('sfp::livewire.admin.form-form-field-edit', [
            'materialTypeOptions' => MaterialType::orderBy('sort_order')->pluck('name', 'slug'),
            'audienceOptions'     => Audience::orderBy('sort_order')->pluck('name', 'slug'),
        ]);
    }
}

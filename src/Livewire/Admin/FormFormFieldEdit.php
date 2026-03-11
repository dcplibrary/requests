<?php

namespace Dcplibrary\Requests\Livewire\Admin;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\FormFieldConfig;
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
    public string $scope = 'both';

    /** @var array{match: string, rules: array<int, array<string, mixed>>} */
    public array $condition = ['match' => 'all', 'rules' => []];
    public bool $hasCondition = false;

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
        $this->scope = $field->scope ?? 'both';

        // Load per-form conditional logic; fall back to the base field's condition so the
        // admin sees the effective condition rather than a blank state when none has been
        // explicitly overridden on the pivot yet.
        $this->condition    = $pivot->conditional_logic ?? ($field?->condition ?? ['match' => 'all', 'rules' => []]);
        $this->hasCondition = ! empty($this->condition['rules']);
        if ($field) {
            $this->fieldKey   = $field->key;
            $this->hasOptions = in_array($field->type, ['select', 'radio'], true);
        }

        // Resolve the Form model ID needed by the options manager.
        $formModel    = Form::bySlug($formSlug);
        $this->formId = $formModel?->id ?? 0;
    }

    /**
     * Persist per-form config and update scope on the base field.
     *
     * When scope expands (e.g. sfp→both), a FormFieldConfig row is created
     * for the other form. When scope narrows (e.g. both→sfp), the config
     * row for the dropped form is removed.
     *
     * @return void
     */
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

        // Update scope on the base field and sync FormFieldConfig rows
        $field = Field::find($this->fieldId);
        if ($field && $field->scope !== $this->scope) {
            $field->update(['scope' => $this->scope]);

            $targetSlugs = $this->scope === 'both' ? ['sfp', 'ill'] : [$this->scope];

            // Create missing FormFieldConfig rows for newly added forms
            foreach ($targetSlugs as $slug) {
                $formModel = Form::bySlug($slug);
                if (! $formModel) {
                    continue;
                }
                $exists = FormFieldConfig::where('form_id', $formModel->id)
                    ->where('field_id', $field->id)
                    ->exists();
                if (! $exists) {
                    $maxOrder = FormFieldConfig::where('form_id', $formModel->id)->max('sort_order') ?? 0;
                    FormFieldConfig::create([
                        'form_id'    => $formModel->id,
                        'field_id'   => $field->id,
                        'sort_order' => $maxOrder + 1,
                        'required'   => $this->required,
                        'visible'    => true,
                    ]);
                }
            }

            // Remove FormFieldConfig rows for forms no longer in scope
            if ($this->scope !== 'both') {
                $droppedSlug = $this->scope === 'sfp' ? 'ill' : 'sfp';
                $droppedForm = Form::bySlug($droppedSlug);
                if ($droppedForm) {
                    FormFieldConfig::where('form_id', $droppedForm->id)
                        ->where('field_id', $field->id)
                        ->delete();
                }
            }
        }

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

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        $mtField  = Field::where('key', 'material_type')->first();
        $audField = Field::where('key', 'audience')->first();

        return view('requests::livewire.admin.form-form-field-edit', [
            'materialTypeOptions' => $mtField
                ? FieldOption::where('field_id', $mtField->id)->active()->ordered()->pluck('name', 'slug')
                : collect(),
            'audienceOptions'     => $audField
                ? FieldOption::where('field_id', $audField->id)->active()->ordered()->pluck('name', 'slug')
                : collect(),
        ]);
    }
}

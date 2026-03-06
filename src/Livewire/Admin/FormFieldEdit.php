<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\Console;
use Dcplibrary\Sfp\Models\FormField;
use Dcplibrary\Sfp\Models\Genre;
use Dcplibrary\Sfp\Models\MaterialType;
use Livewire\Component;

/**
 * Edit a single FormField — label, required, active, and conditional logic.
 * Mounted from the form-fields edit page via @livewire('sfp-admin-form-field-edit').
 */
class FormFieldEdit extends Component
{
    public int $fieldId;

    public string $label     = '';
    public bool   $required  = false;
    public bool   $active    = false;

    /** @var array{match: string, rules: array<int, array<string, mixed>>} */
    public array $condition    = ['match' => 'all', 'rules' => []];
    public bool  $hasCondition = false;

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    public function mount(int $fieldId): void
    {
        $field = FormField::findOrFail($fieldId);

        $this->fieldId      = $fieldId;
        $this->label        = $field->label;
        $this->required     = $field->required;
        $this->active       = $field->active;
        $this->condition    = $field->condition ?? ['match' => 'all', 'rules' => []];
        $this->hasCondition = ! empty($this->condition['rules']);
    }

    // ── Save ──────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->label = trim($this->label);

        $this->validate(['label' => 'required|string|max:100']);

        $condition = ($this->hasCondition && ! empty($this->condition['rules']))
            ? $this->condition
            : null;

        FormField::where('id', $this->fieldId)->update([
            'label'     => $this->label,
            'required'  => $this->required,
            'active'    => $this->active,
            'condition' => $condition ? json_encode($condition) : null,
        ]);

        FormField::bustCache();

        session()->flash('success', "'{$this->label}' updated.");
        $this->redirect(route('sfp.staff.settings.form-fields'));
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

    public function render()
    {
        /** @var FormField $field */
        $field = FormField::findOrFail($this->fieldId);

        $optionFieldConfig = [
            'material_type' => [
                'class'          => MaterialType::class,
                'title'          => 'Material Type Options',
                'conditionField' => 'material_type',
                'extraFields'    => [
                    ['key' => 'has_other_text', 'label' => 'Other text input', 'type' => 'boolean'],
                ],
            ],
            'audience' => [
                'class'          => Audience::class,
                'title'          => 'Audience Options',
                'conditionField' => 'audience',
                'extraFields'    => [
                    ['key' => 'bibliocommons_value', 'label' => 'BiblioCommons value', 'type' => 'text'],
                ],
            ],
            'genre'   => ['class' => Genre::class,   'title' => 'Genre Options',   'conditionField' => null, 'extraFields' => []],
            'console' => ['class' => Console::class, 'title' => 'Console Options', 'conditionField' => null, 'extraFields' => []],
        ];

        return view('sfp::livewire.admin.form-field-edit', [
            'fieldKey'          => $field->key,
            'optionConfig'      => $optionFieldConfig[$field->key] ?? null,
            'materialTypeOptions' => MaterialType::orderBy('sort_order')->pluck('name', 'slug'),
            'audienceOptions'   => Audience::orderBy('sort_order')->pluck('name', 'slug'),
        ]);
    }
}

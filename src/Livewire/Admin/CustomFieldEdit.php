<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\CustomField;
use Livewire\Component;

class CustomFieldEdit extends Component
{
    public int $fieldId;

    public string $label = '';
    public string $key = '';
    public string $type = 'text';
    public int $step = 2;
    public string $requestKind = 'sfp';

    public bool $required = false;
    public bool $active = true;
    public bool $includeAsToken = false;
    public bool $filterable = false;

    /** @var array{match: string, rules: array<int, array<string, mixed>>} */
    public array $condition = ['match' => 'all', 'rules' => []];
    public bool $hasCondition = false;

    public function mount(int $fieldId): void
    {
        $field = CustomField::findOrFail($fieldId);

        $this->fieldId        = $fieldId;
        $this->label          = $field->label;
        $this->key            = $field->key;
        $this->type           = $field->type;
        $this->step           = (int) $field->step;
        $this->requestKind    = $field->request_kind;
        $this->required       = (bool) $field->required;
        $this->active         = (bool) $field->active;
        $this->includeAsToken = (bool) $field->include_as_token;
        $this->filterable     = (bool) $field->filterable;
        $this->condition      = $field->condition ?? ['match' => 'all', 'rules' => []];
        $this->hasCondition   = ! empty($this->condition['rules']);
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

    public function save(): void
    {
        $this->label = trim($this->label);
        $this->key   = trim($this->key);

        $this->validate([
            'label'       => 'required|string|max:100',
            'key'         => 'required|string|max:100',
            'type'        => 'required|string|max:30',
            'step'        => 'required|integer|min:1|max:10',
            'requestKind' => 'required|in:sfp,ill,both',
        ]);

        $condition = ($this->hasCondition && ! empty($this->condition['rules']))
            ? $this->condition
            : null;

        CustomField::whereKey($this->fieldId)->update([
            'label'            => $this->label,
            'key'              => $this->key,
            'type'             => $this->type,
            'step'             => $this->step,
            'request_kind'     => $this->requestKind,
            'required'         => $this->required,
            'active'           => $this->active,
            'include_as_token' => $this->includeAsToken,
            'filterable'       => $this->filterable,
            'condition'        => $condition ? json_encode($condition) : null,
        ]);

        session()->flash('success', "'{$this->label}' updated.");
        $this->redirect(route('request.staff.settings.custom-fields'));
    }

    public function render()
    {
        $field = CustomField::findOrFail($this->fieldId);

        // Condition fields: material_type (ILL uses MaterialType) plus any select/radio custom field keys.
        $conditionFieldKeys = array_values(array_unique(array_merge(
            ['material_type'],
            CustomField::query()
                ->whereIn('type', ['select', 'radio'])
                ->orderBy('sort_order')
                ->pluck('key')
                ->all()
        )));

        // For rule value pickers: custom field options, plus material_type from MaterialType (for ILL conditions).
        $optionsByKey = CustomField::query()
            ->whereIn('key', $conditionFieldKeys)
            ->with(['options' => fn ($q) => $q->active()->ordered()])
            ->get()
            ->mapWithKeys(fn (CustomField $f) => [$f->key => $f->options->pluck('name', 'slug')->all()])
            ->all();

        $optionsByKey['material_type'] = \Dcplibrary\Sfp\Models\MaterialType::query()
            ->where('active', true)
            ->ordered()
            ->get()
            ->pluck('name', 'slug')
            ->all();

        return view('sfp::livewire.admin.custom-field-edit', [
            'field'             => $field,
            'conditionFieldKeys'=> $conditionFieldKeys,
            'optionsByKey'      => $optionsByKey,
            'showOptionsManager'=> in_array($field->type, ['select', 'radio'], true),
        ]);
    }
}


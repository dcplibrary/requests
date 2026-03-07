<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\Audience;
use Dcplibrary\Sfp\Models\Console;
use Dcplibrary\Sfp\Models\FormField;
use Dcplibrary\Sfp\Models\Genre;
use Dcplibrary\Sfp\Models\MaterialType;
use Livewire\Component;

/**
 * Admin component for managing patron-form field order, visibility,
 * required status, and conditional logic.
 *
 * Changes are persisted immediately on every interaction so there is no
 * explicit "Save" button — matching the Gravity Forms-style UX the user
 * requested.
 */
class FormFields extends Component
{
    /** @var array<int, array<string, mixed>> Ordered list of field state arrays */
    public array $fields = [];

    /** @var array<int, bool> Which field indexes have their condition panel open */
    public array $expanded = [];

    /** Key of the field whose Options panel is open, or null if closed */
    public ?string $optionsPanel = null;

    public function mount(): void
    {
        $this->loadFromDb();
    }

    // ── Data loading ─────────────────────────────────────────────────────────

    private function loadFromDb(): void
    {
        $this->fields = FormField::orderBy('sort_order')
            ->get()
            ->map(fn (FormField $f) => [
                'id'           => $f->id,
                'key'          => $f->key,
                'label'        => $f->label,
                'active'       => $f->active,
                'required'     => $f->required,
                'include_as_token' => (bool) $f->include_as_token,
                'has_condition'=> ! empty($f->condition['rules']),
                'condition'    => $f->condition ?? ['match' => 'all', 'rules' => []],
            ])
            ->values()
            ->toArray();

        $this->expanded = array_fill(0, count($this->fields), false);
    }

    // ── Reordering ───────────────────────────────────────────────────────────

    public function moveUp(int $index): void
    {
        if ($index <= 0) {
            return;
        }

        [$this->fields[$index - 1], $this->fields[$index]] =
            [$this->fields[$index], $this->fields[$index - 1]];

        [$this->expanded[$index - 1], $this->expanded[$index]] =
            [$this->expanded[$index], $this->expanded[$index - 1]];

        $this->persist();
    }

    public function moveDown(int $index): void
    {
        if ($index >= count($this->fields) - 1) {
            return;
        }

        [$this->fields[$index + 1], $this->fields[$index]] =
            [$this->fields[$index], $this->fields[$index + 1]];

        [$this->expanded[$index + 1], $this->expanded[$index]] =
            [$this->expanded[$index], $this->expanded[$index + 1]];

        $this->persist();
    }

    // ── Toggles ──────────────────────────────────────────────────────────────

    public function toggleActive(int $index): void
    {
        $this->fields[$index]['active'] = ! $this->fields[$index]['active'];
        $this->persist();
    }

    public function toggleRequired(int $index): void
    {
        $this->fields[$index]['required'] = ! $this->fields[$index]['required'];
        $this->persist();
    }

    public function toggleConditionPanel(int $index): void
    {
        $this->expanded[$index] = ! $this->expanded[$index];
    }

    public function toggleOptionsPanel(string $key): void
    {
        $this->optionsPanel = ($this->optionsPanel === $key) ? null : $key;
    }


    public function updateLabel(int $fieldId, string $label): void
    {
        $label = trim($label);
        if ($label === '') {
            return;
        }

        FormField::where('id', $fieldId)->update(['label' => $label]);

        // Sync local state so the blade reflects the new label without a full reload
        foreach ($this->fields as $i => $field) {
            if ($field['id'] === $fieldId) {
                $this->fields[$i]['label'] = $label;
                break;
            }
        }

        FormField::bustCache();
    }

    public function toggleHasCondition(int $index): void
    {
        $this->fields[$index]['has_condition'] = ! $this->fields[$index]['has_condition'];

        if ($this->fields[$index]['has_condition']
            && empty($this->fields[$index]['condition']['rules'])) {
            $this->fields[$index]['condition'] = ['match' => 'all', 'rules' => []];
        }

        $this->persist();
    }

    // ── Condition rule management ─────────────────────────────────────────────

    public function setConditionMatch(int $fieldIndex, string $match): void
    {
        $this->fields[$fieldIndex]['condition']['match'] = $match;
        $this->persist();
    }

    public function addRule(int $fieldIndex): void
    {
        $this->fields[$fieldIndex]['condition']['rules'][] = [
            'field'    => 'material_type',
            'operator' => 'in',
            'values'   => [],
        ];
        $this->persist();
    }

    public function removeRule(int $fieldIndex, int $ruleIndex): void
    {
        array_splice($this->fields[$fieldIndex]['condition']['rules'], $ruleIndex, 1);
        $this->persist();
    }

    public function setRuleField(int $fieldIndex, int $ruleIndex, string $field): void
    {
        $this->fields[$fieldIndex]['condition']['rules'][$ruleIndex]['field']  = $field;
        $this->fields[$fieldIndex]['condition']['rules'][$ruleIndex]['values'] = [];
        $this->persist();
    }

    public function setRuleOperator(int $fieldIndex, int $ruleIndex, string $operator): void
    {
        $this->fields[$fieldIndex]['condition']['rules'][$ruleIndex]['operator'] = $operator;
        $this->persist();
    }

    public function toggleRuleValue(int $fieldIndex, int $ruleIndex, string $value): void
    {
        $values = $this->fields[$fieldIndex]['condition']['rules'][$ruleIndex]['values'] ?? [];

        if (in_array($value, $values, true)) {
            $values = array_values(array_filter($values, fn ($v) => $v !== $value));
        } else {
            $values[] = $value;
        }

        $this->fields[$fieldIndex]['condition']['rules'][$ruleIndex]['values'] = $values;
        $this->persist();
    }

    // ── Persistence ──────────────────────────────────────────────────────────

    private function persist(): void
    {
        foreach ($this->fields as $i => $field) {
            $condition = ($field['has_condition'] && ! empty($field['condition']['rules']))
                ? $field['condition']
                : null;

            FormField::where('id', $field['id'])->update([
                'sort_order' => $i + 1,
                'active'     => $field['active'],
                'required'   => $field['required'],
                'condition'  => $condition ? json_encode($condition) : null,
            ]);
        }

        FormField::bustCache();
    }

    // ── Render ───────────────────────────────────────────────────────────────

    public function render()
    {
        return view('sfp::livewire.admin.form-fields', [
            'materialTypeOptions' => MaterialType::orderBy('sort_order')
                ->pluck('name', 'slug'),
            'audienceOptions' => Audience::orderBy('sort_order')
                ->pluck('name', 'slug'),
            // Fields that have an inline Options panel — key => [class, title, extraFields]
            'optionFieldConfig'  => [
                'material_type' => [
                    'class'          => MaterialType::class,
                    'title'          => 'Material Type Options',
                    'conditionField' => 'material_type',
                    'extraFields'    => [
                        ['key' => 'has_other_text', 'label' => 'Other text', 'type' => 'boolean'],
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
            ],
        ]);
    }
}

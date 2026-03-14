<?php

namespace Dcplibrary\Requests\Livewire\Admin;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Dcplibrary\Requests\Models\FormFieldOptionOverride;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

/**
 * Admin component for managing field order and conditions per form.
 * Two separate lists (Suggest for Purchase, Interlibrary Loan) so each can be controlled independently.
 */
class FormFields extends Component
{
    /** @var array<int, array<string, mixed>> Suggest for Purchase form field configs */
    public array $suggestFields = [];

    /** @var array<int, array<string, mixed>> ILL form field configs */
    public array $illFields = [];

    /** Active tab: 'sfp' | 'ill' */
    public string $activeFormTab = 'sfp';

    public function mount(): void
    {
        $tab = request()->query('tab');
        if (in_array($tab, ['sfp', 'ill'], true)) {
            $this->activeFormTab = $tab;
        }
        $this->loadFromDb();
    }

    private function loadFromDb(): void
    {
        $formSfp = Form::bySlug('sfp');
        $formIll = Form::bySlug('ill');
        if (! $formSfp || ! $formIll) {
            $this->suggestFields = [];
            $this->illFields = [];
            return;
        }

        $this->suggestFields = $this->loadFormFields($formSfp, 'sfp');
        $this->illFields = $this->loadFormFields($formIll, 'ill');
    }

    /**
     * Load all field configs for a given form.
     *
     * @param  Form    $form
     * @param  string  $formSlug
     * @return array<int, array<string, mixed>>
     */
    private function loadFormFields(Form $form, string $formSlug): array
    {
        return FormFieldConfig::where('form_id', $form->id)
            ->with('field')
            ->orderBy('sort_order')
            ->get()
            ->map(function (FormFieldConfig $cfg) use ($formSlug) {
                $f = $cfg->field;
                if (! $f) {
                    return null;
                }
                $condition = $cfg->conditional_logic ?? $f->condition ?? ['match' => 'all', 'rules' => []];
                return [
                    'pivot_id'          => $cfg->id,
                    'id'                => $f->id,
                    'key'               => $f->key,
                    'label'             => $cfg->label_override !== null && $cfg->label_override !== '' ? $cfg->label_override : $f->label,
                    'type'              => $f->type ?? 'text',
                    'scope'             => $f->scope ?? 'both',
                    'active'            => (bool) $cfg->visible,
                    'required'          => (bool) $cfg->required,
                    'include_as_token'  => (bool) $f->include_as_token,
                    'has_condition'     => ! empty($condition['rules']),
                    'condition'         => $condition,
                    'sort_order'        => $cfg->sort_order,
                    'edit_url'          => $this->fieldEditUrl($f->id, $formSlug),
                ];
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /** Build URL for per-form field config edit. */
    private function fieldEditUrl(int $fieldId, string $formSlug): string
    {
        $prefix = trim(config('requests.route_prefix', 'request'), '/');
        $path = '/' . $prefix . '/staff/settings/form-fields/' . $fieldId . '/form/' . $formSlug . '/edit';

        return URL::to($path);
    }

    public function moveUpSuggest(int $index): void
    {
        if ($index <= 0) {
            return;
        }
        $this->suggestFields = array_values($this->suggestFields);
        [$this->suggestFields[$index - 1], $this->suggestFields[$index]] =
            [$this->suggestFields[$index], $this->suggestFields[$index - 1]];
        $this->suggestFields = array_values($this->suggestFields);
        $this->persistSuggest();
    }

    public function moveDownSuggest(int $index): void
    {
        if ($index >= count($this->suggestFields) - 1) {
            return;
        }
        $this->suggestFields = array_values($this->suggestFields);
        [$this->suggestFields[$index + 1], $this->suggestFields[$index]] =
            [$this->suggestFields[$index], $this->suggestFields[$index + 1]];
        $this->suggestFields = array_values($this->suggestFields);
        $this->persistSuggest();
    }

    public function moveUpIll(int $index): void
    {
        if ($index <= 0) {
            return;
        }
        $this->illFields = array_values($this->illFields);
        [$this->illFields[$index - 1], $this->illFields[$index]] =
            [$this->illFields[$index], $this->illFields[$index - 1]];
        $this->illFields = array_values($this->illFields);
        $this->persistIll();
    }

    public function moveDownIll(int $index): void
    {
        if ($index >= count($this->illFields) - 1) {
            return;
        }
        $this->illFields = array_values($this->illFields);
        [$this->illFields[$index + 1], $this->illFields[$index]] =
            [$this->illFields[$index], $this->illFields[$index + 1]];
        $this->illFields = array_values($this->illFields);
        $this->persistIll();
    }

    private function persistSuggest(): void
    {
        $rows = array_values($this->suggestFields);
        foreach ($rows as $i => $row) {
            $order = $i + 1;
            FormFieldConfig::where('id', $row['pivot_id'])->update(['sort_order' => $order]);
            $this->suggestFields[$i]['sort_order'] = $order;
        }
    }

    private function persistIll(): void
    {
        $rows = array_values($this->illFields);
        foreach ($rows as $i => $row) {
            $order = $i + 1;
            FormFieldConfig::where('id', $row['pivot_id'])->update(['sort_order' => $order]);
            $this->illFields[$i]['sort_order'] = $order;
        }
    }

    /**
     * Soft-delete a field and cascade-remove related config/option rows.
     *
     * @param  int  $fieldId
     * @return void
     */
    public function deleteField(int $fieldId): void
    {
        $field = Field::findOrFail($fieldId);
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

            // Soft-delete options, then the field itself
            $field->options()->delete();
            $field->delete();
        });

        session()->flash('success', "'{$label}' has been deleted.");
        $this->loadFromDb();
    }

    /**
     * @return \Illuminate\Contracts\View\View
     */
    public function render()
    {
        return view('requests::livewire.admin.form-fields');
    }
}

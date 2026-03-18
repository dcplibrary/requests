<?php

namespace Dcplibrary\Requests\Livewire\Admin;

use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Dcplibrary\Requests\Models\FormFieldOptionOverride;
use Livewire\Component;

/**
 * Edit a single option's per-form configuration for a FormField.
 *
 * Writes to form_form_field_options.  Editable: label override, visible.
 * The option itself (name, slug) is defined globally and not editable here.
 */
class FormFormFieldOptionEdit extends Component
{
    public int    $fieldId;
    public string $optionSlug;
    public string $formSlug = '';

    public string $optionName    = '';
    public string $labelOverride = '';
    public bool   $visible       = true;

    /**
     * @param  int     $fieldId
     * @param  string  $optionSlug
     * @param  string  $formSlug
     * @return void
     */
    public function mount(int $fieldId, string $optionSlug, string $formSlug): void
    {
        $this->fieldId    = $fieldId;
        $this->optionSlug = $optionSlug;
        $this->formSlug   = $formSlug;

        // Look up the option's display name from FieldOption.
        $option = FieldOption::where('field_id', $fieldId)
            ->where('slug', $optionSlug)
            ->first();
        $this->optionName = $option?->name ?? $optionSlug;

        // Load any existing per-form override.
        $formModel = Form::bySlug($formSlug);
        if ($formModel) {
            $override = FormFieldOptionOverride::where('form_id', $formModel->id)
                ->where('field_id', $fieldId)
                ->where('option_slug', $optionSlug)
                ->first();

            if ($override) {
                $this->labelOverride = (string) ($override->label_override ?? '');
                $this->visible       = (bool) $override->visible;
            }
        }
    }

    public function save(): void
    {
        $this->validate([
            'labelOverride' => 'nullable|string|max:150',
        ]);

        $formModel = Form::bySlug($this->formSlug);
        if (! $formModel) {
            return;
        }

        $field = Field::find($this->fieldId);
        if (! $field) {
            return;
        }

        // Ensure the parent FormFieldConfig exists before creating option override.
        FormFieldConfig::firstOrCreate(
            ['form_id' => $formModel->id, 'field_id' => $this->fieldId],
            ['sort_order' => 0, 'required' => false, 'visible' => true]
        );

        $labelOverride = trim($this->labelOverride);

        FormFieldOptionOverride::upsertForForm(
            $formModel->id,
            $this->fieldId,
            $this->optionSlug,
            [
                'label_override' => $labelOverride !== '' ? $labelOverride : null,
                'visible' => $this->visible,
            ]
        );

        session()->flash('success', 'Option settings saved.');
        $this->redirect(route('request.staff.settings.form-fields.edit-for-form', [
            'field' => $this->fieldId,
            'form'  => $this->formSlug,
        ]));
    }

    public function render()
    {
        return view('requests::livewire.admin.form-form-field-option-edit');
    }
}

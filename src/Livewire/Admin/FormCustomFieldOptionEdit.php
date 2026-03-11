<?php

namespace Dcplibrary\Requests\Livewire\Admin;

use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\FormFieldOptionOverride;
use Livewire\Component;

/**
 * Edit a single option's per-form configuration for a CustomField.
 *
 * Writes to form_custom_field_options.  Editable: label override, visible.
 * The option's base definition (name, slug) is not editable here.
 */
class FormCustomFieldOptionEdit extends Component
{
    public int    $fieldId;
    public int    $optionId;
    public string $formSlug = '';

    public string $optionName    = '';
    public string $labelOverride = '';
    public bool   $visible       = true;

    public function mount(int $fieldId, int $optionId, string $formSlug): void
    {
        $this->fieldId    = $fieldId;
        $this->optionId   = $optionId;
        $this->formSlug   = $formSlug;

        $option = FieldOption::findOrFail($optionId);
        $this->optionName = $option->name;

        $formModel = Form::bySlug($formSlug);
        if ($formModel) {
            $override = FormFieldOptionOverride::where('form_id', $formModel->id)
                ->where('field_id', $fieldId)
                ->where('option_slug', $option->slug)
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

        $option = FieldOption::find($this->optionId);
        if (! $option) {
            return;
        }

        $labelOverride = trim($this->labelOverride);

        FormFieldOptionOverride::updateOrInsert(
            [
                'form_id'     => $formModel->id,
                'field_id'    => $this->fieldId,
                'option_slug' => $option->slug,
            ],
            [
                'label_override' => $labelOverride !== '' ? $labelOverride : null,
                'visible'        => $this->visible,
            ]
        );

        session()->flash('success', 'Option settings saved.');
        $this->redirect(route('request.staff.settings.custom-fields.edit-for-form', [
            'field' => $this->fieldId,
            'form'  => $this->formSlug,
        ]));
    }

    public function render()
    {
        return view('requests::livewire.admin.form-custom-field-option-edit');
    }
}

<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\CustomFieldOption;
use Dcplibrary\Sfp\Models\Form;
use Dcplibrary\Sfp\Models\FormCustomFieldOption;
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

        $option = CustomFieldOption::findOrFail($optionId);
        $this->optionName = $option->name;

        $formModel = Form::bySlug($formSlug);
        if ($formModel) {
            $override = FormCustomFieldOption::where('form_id', $formModel->id)
                ->where('custom_field_option_id', $optionId)
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

        $labelOverride = trim($this->labelOverride);

        FormCustomFieldOption::updateOrInsert(
            [
                'form_id'               => $formModel->id,
                'custom_field_option_id'=> $this->optionId,
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
        return view('sfp::livewire.admin.form-custom-field-option-edit');
    }
}

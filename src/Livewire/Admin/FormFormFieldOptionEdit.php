<?php

namespace Dcplibrary\Sfp\Livewire\Admin;

use Dcplibrary\Sfp\Models\Form;
use Dcplibrary\Sfp\Models\FormField;
use Dcplibrary\Sfp\Models\FormFormFieldOption;
use Dcplibrary\Sfp\Models\FormFormField;
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

    public function mount(int $fieldId, string $optionSlug, string $formSlug): void
    {
        $this->fieldId    = $fieldId;
        $this->optionSlug = $optionSlug;
        $this->formSlug   = $formSlug;

        // Look up the option's display name from the correct model.
        $fieldKey  = FormField::findOrFail($fieldId)->key;
        $modelClass = FormFormFieldOptionsManager::modelClassForKey($fieldKey);
        $option = $modelClass ? $modelClass::where('slug', $optionSlug)->first() : null;
        $this->optionName = $option?->name ?? $optionSlug;

        // Load any existing per-form override.
        $formModel = Form::bySlug($formSlug);
        if ($formModel) {
            $override = FormFormFieldOption::where('form_id', $formModel->id)
                ->where('form_field_id', $fieldId)
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

        $formField = FormField::find($this->fieldId);
        if (! $formField) {
            return;
        }

        // Ensure the parent FormFormField pivot exists before creating option override.
        $pivot = FormFormField::firstOrCreate(
            ['form_id' => $formModel->id, 'form_field_id' => $this->fieldId],
            ['sort_order' => 0, 'required' => false, 'visible' => true]
        );

        $labelOverride = trim($this->labelOverride);

        FormFormFieldOption::updateOrInsert(
            [
                'form_id'       => $formModel->id,
                'form_field_id' => $this->fieldId,
                'option_slug'   => $this->optionSlug,
            ],
            [
                'label_override' => $labelOverride !== '' ? $labelOverride : null,
                'visible'        => $this->visible,
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
        return view('sfp::livewire.admin.form-form-field-option-edit');
    }
}

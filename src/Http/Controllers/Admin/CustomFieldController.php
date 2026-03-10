<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\CustomFieldOption;
use Dcplibrary\Sfp\Models\Form;
use Dcplibrary\Sfp\Models\FormCustomField;

class CustomFieldController extends Controller
{
    public function index()
    {
        return redirect()->route('request.staff.settings.form-fields');
    }

    public function edit(CustomField $field)
    {
        return view('sfp::staff.custom-fields.edit', compact('field'));
    }

    /**
     * Edit this custom field's configuration for one form only (SFP or ILL).
     * Edits the pivot (label override, required, visible, conditional_logic).
     * Creates the pivot row if missing.
     */
    public function editForForm(CustomField $field, string $form)
    {
        abort_unless(in_array($form, ['sfp', 'ill'], true), 404);

        $formModel = Form::bySlug($form);
        if (! $formModel) {
            abort(404);
        }

        $maxOrder = FormCustomField::where('form_id', $formModel->id)->max('sort_order') ?? 0;
        $pivot = FormCustomField::firstOrCreate(
            [
                'form_id'         => $formModel->id,
                'custom_field_id' => $field->id,
            ],
            [
                'sort_order'        => $maxOrder + 1,
                'conditional_logic' => $field->condition,
                'required'          => $field->required,
                'visible'           => true,
            ]
        );

        $formLabel = $form === 'ill' ? 'Interlibrary Loan' : 'Suggest for Purchase';

        return view('sfp::staff.custom-fields.edit-for-form', [
            'field'     => $field,
            'pivot'     => $pivot,
            'formSlug'  => $form,
            'formLabel' => $formLabel,
        ]);
    }

    /**
     * Edit a single option's per-form configuration.
     * Only valid for select/radio custom fields.
     */
    public function editForFormOption(CustomField $field, string $form, int $optionId)
    {
        abort_unless(in_array($form, ['sfp', 'ill'], true), 404);
        abort_unless(in_array($field->type, ['select', 'radio'], true), 404, 'This field does not have per-form option overrides.');

        $option = CustomFieldOption::where('id', $optionId)
            ->where('custom_field_id', $field->id)
            ->first();
        abort_unless($option !== null, 404);

        $formLabel  = $form === 'ill' ? 'Interlibrary Loan' : 'Suggest for Purchase';
        $optionName = $option->name;

        return view('sfp::staff.custom-fields.edit-option-for-form', compact(
            'field', 'formLabel', 'optionName'
        ) + ['formSlug' => $form, 'optionId' => $optionId]);
    }
}

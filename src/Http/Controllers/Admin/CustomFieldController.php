<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\FormFieldConfig;
use Dcplibrary\Requests\Models\PatronRequest;

/**
 * Legacy custom-field routes — redirects to the unified form-fields UI.
 */
class CustomFieldController extends Controller
{
    /**
     * Redirect to the unified form-fields index.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function index()
    {
        return redirect()->route('request.staff.settings.form-fields');
    }

    /**
     * Edit a field (base definition).
     *
     * @param  Field  $field
     * @return \Illuminate\View\View
     */
    public function edit(Field $field)
    {
        return view('requests::staff.custom-fields.edit', compact('field'));
    }

    /**
     * Edit this field's per-form configuration (SFP or ILL).
     *
     * Creates the FormFieldConfig row if missing.
     *
     * @param  Field   $field
     * @param  string  $form  sfp|ill
     * @return \Illuminate\View\View
     */
    public function editForForm(Field $field, string $form)
    {
        abort_unless(in_array($form, PatronRequest::kinds(), true), 404);

        $formModel = Form::bySlug($form);
        if (! $formModel) {
            abort(404);
        }

        $maxOrder = FormFieldConfig::where('form_id', $formModel->id)->max('sort_order') ?? 0;
        $pivot = FormFieldConfig::firstOrCreate(
            [
                'form_id'  => $formModel->id,
                'field_id' => $field->id,
            ],
            [
                'sort_order'        => $maxOrder + 1,
                'conditional_logic' => $field->condition,
                'required'          => $field->required,
                'visible'           => true,
            ]
        );

        $formLabel = request_form_name($form);

        return view('requests::staff.custom-fields.edit-for-form', [
            'field'     => $field,
            'pivot'     => $pivot,
            'formSlug'  => $form,
            'formLabel' => $formLabel,
        ]);
    }

    /**
     * Edit a single option's per-form configuration.
     *
     * Only valid for select/radio fields.
     *
     * @param  Field   $field
     * @param  string  $form      sfp|ill
     * @param  int     $optionId  FieldOption ID
     * @return \Illuminate\View\View
     */
    public function editForFormOption(Field $field, string $form, int $optionId)
    {
        abort_unless(in_array($form, PatronRequest::kinds(), true), 404);
        abort_unless(in_array($field->type, ['select', 'radio'], true), 404, 'This field does not have per-form option overrides.');

        $option = FieldOption::where('id', $optionId)
            ->where('field_id', $field->id)
            ->first();
        abort_unless($option !== null, 404);

        $formLabel  = request_form_name($form);
        $optionName = $option->name;

        return view('requests::staff.custom-fields.edit-option-for-form', [
            'field'      => $field,
            'formSlug'   => $form,
            'formLabel'  => $formLabel,
            'optionName' => $optionName,
            'optionId'   => $optionId,
        ]);
    }
}

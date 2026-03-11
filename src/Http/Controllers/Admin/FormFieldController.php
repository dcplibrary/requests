<?php

namespace Dcplibrary\Requests\Http\Controllers\Admin;

use Dcplibrary\Requests\Http\Controllers\Controller;
use Dcplibrary\Requests\Models\Field;
use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\Form;
use Dcplibrary\Requests\Models\FormFieldConfig;

/**
 * Manages field configurations within forms (SFP / ILL).
 */
class FormFieldController extends Controller
{
    /**
     * List all form fields.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('requests::staff.form-fields.index');
    }

    /**
     * Edit this field's configuration for one form only (SFP or ILL).
     *
     * Edits the per-form config (required, visible, conditional_logic); the base
     * field definition is unchanged. Creates the config row if missing.
     *
     * @param  Field   $field
     * @param  string  $form  sfp|ill
     * @return \Illuminate\View\View
     */
    public function editForForm(Field $field, string $form)
    {
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

        $formLabel = $form === 'ill' ? 'Interlibrary Loan' : 'Suggest for Purchase';

        return view('requests::staff.form-fields.edit-for-form', [
            'field'     => $field,
            'pivot'     => $pivot,
            'formSlug'  => $form,
            'formLabel' => $formLabel,
        ]);
    }

    /**
     * Edit a single option's per-form configuration.
     *
     * Only valid for select/radio fields (material_type, audience, genre, etc.).
     *
     * @param  Field   $field
     * @param  string  $form  sfp|ill
     * @param  string  $slug  FieldOption slug
     * @return \Illuminate\View\View
     */
    public function editForFormOption(Field $field, string $form, string $slug)
    {
        abort_unless(in_array($form, ['sfp', 'ill'], true), 404);
        abort_unless(in_array($field->type, ['select', 'radio']), 404, 'This field does not have per-form option overrides.');

        $option = FieldOption::where('field_id', $field->id)->where('slug', $slug)->first();
        abort_unless($option !== null, 404);

        $formLabel  = $form === 'ill' ? 'Interlibrary Loan' : 'Suggest for Purchase';
        $optionName = $option->name;

        return view('requests::staff.form-fields.edit-option-for-form', [
            'field'      => $field,
            'form'       => $form,
            'formSlug'   => $form,
            'formLabel'  => $formLabel,
            'optionName' => $optionName,
            'optionSlug' => $slug,
        ]);
    }

    /**
     * Edit the base field (label, key, active, token) — shared across forms.
     *
     * @param  Field  $field
     * @return \Illuminate\View\View
     */
    public function edit(Field $field)
    {
        return view('requests::staff.form-fields.edit', compact('field'));
    }
}

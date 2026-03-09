<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Models\Form;
use Dcplibrary\Sfp\Models\FormField;
use Dcplibrary\Sfp\Models\FormFormField;

class FormFieldController extends Controller
{
    public function index()
    {
        return view('sfp::staff.form-fields.index');
    }

    /**
     * Edit this field’s configuration for one form only (SFP or ILL).
     * Edits the pivot (required, visible, conditional_logic); base field is unchanged.
     * Creates the pivot row if missing (e.g. seeder not run or new field).
     */
    public function editForForm(FormField $field, string $form)
    {
        $formModel = Form::bySlug($form);
        if (! $formModel) {
            abort(404);
        }
        $maxOrder = FormFormField::where('form_id', $formModel->id)->max('sort_order') ?? 0;
        $pivot = FormFormField::firstOrCreate(
            [
                'form_id'      => $formModel->id,
                'form_field_id'=> $field->id,
            ],
            [
                'sort_order'         => $maxOrder + 1,
                'conditional_logic'  => $field->condition,
                'required'           => $field->required,
                'visible'            => true,
            ]
        );
        $formLabel = $form === 'ill' ? 'Inter-Library Loan' : 'Suggest for Purchase';

        return view('sfp::staff.form-fields.edit-for-form', [
            'field'      => $field,
            'pivot'      => $pivot,
            'formSlug'   => $form,
            'formLabel'  => $formLabel,
        ]);
    }

    /** Edit the base field (label, key, active, token) — shared across forms. */
    public function edit(FormField $field)
    {
        return view('sfp::staff.form-fields.edit', compact('field'));
    }
}

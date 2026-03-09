<?php

namespace Dcplibrary\Sfp\Http\Controllers\Admin;

use Dcplibrary\Sfp\Http\Controllers\Controller;
use Dcplibrary\Sfp\Livewire\Admin\FormFormFieldOptionsManager;
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
     * Edit this field's configuration for one form only (SFP or ILL).
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

    /**
     * Edit a single option's per-form configuration.
     * Only valid for option-type fields (material_type, audience, genre, console).
     */
    public function editForFormOption(FormField $field, string $form, string $slug)
    {
        abort_unless(in_array($form, ['sfp', 'ill'], true), 404);

        $modelClass = FormFormFieldOptionsManager::modelClassForKey($field->key);
        abort_unless($modelClass !== null, 404, 'This field does not have per-form option overrides.');

        $option = $modelClass::where('slug', $slug)->first();
        abort_unless($option !== null, 404);

        $formLabel  = $form === 'ill' ? 'Inter-Library Loan' : 'Suggest for Purchase';
        $optionName = $option->name;

        return view('sfp::staff.form-fields.edit-option-for-form', compact(
            'field', 'form', 'formLabel', 'optionName', 'formLabel'
        ) + ['formSlug' => $form, 'optionSlug' => $slug]);
    }

    /** Edit the base field (label, key, active, token) — shared across forms. */
    public function edit(FormField $field)
    {
        return view('sfp::staff.form-fields.edit', compact('field'));
    }
}

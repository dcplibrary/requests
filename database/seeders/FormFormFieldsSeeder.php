<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\Form;
use Dcplibrary\Sfp\Models\FormCustomField;
use Dcplibrary\Sfp\Models\FormFormField;
use Dcplibrary\Sfp\Models\FormField;
use Illuminate\Database\Seeder;

/**
 * Populates form_form_fields from sfp_form_fields using form_scope.
 * Also attaches ILL custom fields to the ILL form (form_custom_fields).
 * Run after FormsSeeder, FormFieldsSeeder, and IllCustomFieldsSeeder.
 * Global fields get a row for both SFP and ILL; sfp/ill scope get one row for that form.
 */
class FormFormFieldsSeeder extends Seeder
{
    public function run(): void
    {
        $formSfp = Form::bySlug('sfp');
        $formIll = Form::bySlug('ill');
        if (! $formSfp || ! $formIll) {
            return;
        }

        $formFields = FormField::orderBy('sort_order')->get();
        foreach ($formFields as $field) {
            $scope = $field->form_scope ?? 'global';
            $conditionalLogic = $field->condition;
            $required = (bool) $field->required;
            $sortOrder = (int) $field->sort_order;

            if ($scope === 'global') {
                FormFormField::updateOrCreate(
                    ['form_id' => $formSfp->id, 'form_field_id' => $field->id],
                    ['sort_order' => $sortOrder, 'conditional_logic' => $conditionalLogic, 'required' => $required, 'visible' => true]
                );
                FormFormField::updateOrCreate(
                    ['form_id' => $formIll->id, 'form_field_id' => $field->id],
                    ['sort_order' => $sortOrder, 'conditional_logic' => $conditionalLogic, 'required' => $required, 'visible' => true]
                );
            } elseif ($scope === 'sfp') {
                FormFormField::updateOrCreate(
                    ['form_id' => $formSfp->id, 'form_field_id' => $field->id],
                    ['sort_order' => $sortOrder, 'conditional_logic' => $conditionalLogic, 'required' => $required, 'visible' => true]
                );
            } elseif ($scope === 'ill') {
                FormFormField::updateOrCreate(
                    ['form_id' => $formIll->id, 'form_field_id' => $field->id],
                    ['sort_order' => $sortOrder, 'conditional_logic' => $conditionalLogic, 'required' => $required, 'visible' => true]
                );
            }
        }

        // Attach SFP custom fields to the SFP form so they appear in the Forms admin list
        $maxSfpFormOrder = FormFormField::where('form_id', $formSfp->id)->max('sort_order') ?? 0;
        $sfpCustomFields = CustomField::whereIn('request_kind', ['sfp', 'both'])->orderBy('sort_order')->get();
        foreach ($sfpCustomFields as $i => $cf) {
            FormCustomField::updateOrCreate(
                ['form_id' => $formSfp->id, 'custom_field_id' => $cf->id],
                [
                    'sort_order' => $maxSfpFormOrder + 1 + $i,
                    'required'   => (bool) $cf->required,
                    'visible'    => true,
                    'step'       => (int) $cf->step,
                ]
            );
        }

        // Attach ILL custom fields to the ILL form so they appear in the ILL list
        $maxIllFormOrder = FormFormField::where('form_id', $formIll->id)->max('sort_order') ?? 0;
        $illCustomFields = CustomField::whereIn('request_kind', ['ill', 'both'])->orderBy('sort_order')->get();
        foreach ($illCustomFields as $i => $cf) {
            FormCustomField::updateOrCreate(
                ['form_id' => $formIll->id, 'custom_field_id' => $cf->id],
                [
                    'sort_order' => $maxIllFormOrder + 1 + $i,
                    'required'   => (bool) $cf->required,
                    'visible'    => true,
                    'step'       => (int) $cf->step,
                ]
            );
        }
    }
}

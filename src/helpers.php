<?php

use Dcplibrary\Requests\Models\Form;

if (! function_exists('request_form_name')) {
    /**
     * Get the display name for a request form by slug.
     *
     * Uses the Form model (e.g. "Suggest for Purchase", "Interlibrary Loan").
     * Falls back to the slug when the form is not found in the database.
     *
     * @param  string  $formSlug  Form slug (e.g. 'sfp', 'ill'). Use {@see \Dcplibrary\Requests\Models\PatronRequest::KIND_SFP} or KIND_ILL for consistency.
     * @return string  The form's display name, or the slug if not found.
     */
    function request_form_name(string $formSlug): string
    {
        $form = Form::bySlug($formSlug);

        return $form?->name ?? $formSlug;
    }
}

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

if (! function_exists('requests_safe_html')) {
    /**
     * Reduce untrusted HTML (e.g. ISBNdb synopsis/overview) to a small set of
     * formatting tags with all attributes removed, so it is safe to render with {!! !!}.
     *
     * @param  string|null  $html
     * @return string|null
     */
    function requests_safe_html(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        $allowed = ['p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li'];
        $clean   = strip_tags($html, $allowed);

        // Drop every attribute from the surviving tags (onclick, style, href, ...).
        return (string) preg_replace(
            '#<(/?)(' . implode('|', $allowed) . ')\b[^>]*>#i',
            '<$1$2>',
            $clean
        );
    }
}

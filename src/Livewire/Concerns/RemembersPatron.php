<?php

namespace Dcplibrary\Requests\Livewire\Concerns;

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

/**
 * Shared patron session helpers for SFP and ILL forms.
 *
 * Expects the consuming component to declare public properties:
 *   string $barcode, $name_first, $name_last, $phone, $email
 * Optional: bool $notify_by_email (RequestForm, IllForm)
 *
 * Also provides $turnstileToken — the cf-turnstile-response value set by the
 * Cloudflare Turnstile widget via its JS callback. Verified in nextStep() when
 * captcha_enabled is true.
 */
trait RemembersPatron
{
    /** Cloudflare Turnstile token, set by the widget's JS success callback. */
    public string $turnstileToken = '';

    /**
     * Set server-side once step 1 (CAPTCHA, patron validation, barcode check) has passed.
     * Locked so the browser cannot flip it and skip straight to submit().
     */
    #[Locked]
    public bool $patronVerified = false;

    /** Barcode that passed step 1; the patron must not swap it afterwards. */
    #[Locked]
    public string $verifiedBarcode = '';

    /** Session key used to carry step-1 verification across the SFP → ILL hand-off. */
    protected const VERIFIED_BARCODE_SESSION_KEY = 'request.verified_barcode';

    /**
     * Record that step 1 passed for the current barcode.
     */
    protected function markPatronVerified(): void
    {
        $this->patronVerified  = true;
        $this->verifiedBarcode = $this->barcode;
    }

    /**
     * Guard for every action after step 1 (search, accept/skip match, submit, hand-off).
     *
     * Returns false and sends the patron back to step 1 when step 1 never passed in this
     * component, or the barcode was changed afterwards. Re-validates the patron fields,
     * which stay editable in the browser.
     */
    protected function ensurePatronVerified(): bool
    {
        if (! $this->patronVerified || ! hash_equals($this->verifiedBarcode, $this->barcode)) {
            $this->patronVerified  = false;
            $this->verifiedBarcode = '';
            $this->step            = 1;
            $this->processing      = false;
            $this->addError('barcode', 'Please confirm your library card details to continue.');

            return false;
        }

        try {
            $this->validate($this->patronValidationRules());
        } catch (ValidationException $e) {
            $this->step       = 1;
            $this->processing = false;
            throw $e;
        }

        return true;
    }

    /**
     * Hydrate patron fields from the session (e.g. after "Submit Another Request").
     *
     * @return void
     */
    protected function hydratePatronFromSession(): void
    {
        $remembered = session('request.patron');
        if (is_array($remembered)) {
            $this->barcode    = (string) ($remembered['barcode'] ?? $this->barcode);
            $this->name_first = (string) ($remembered['name_first'] ?? $this->name_first);
            $this->name_last  = (string) ($remembered['name_last'] ?? $this->name_last);
            $this->phone      = (string) ($remembered['phone'] ?? $this->phone);
            $this->email      = (string) ($remembered['email'] ?? $this->email);
            if (property_exists($this, 'notify_by_email')) {
                $this->notify_by_email = (bool) ($remembered['notify_by_email'] ?? false);
            }
        }
    }

    /**
     * Persist patron fields to the session so they survive page reloads / form switches.
     *
     * @return void
     */
    protected function savePatronToSession(): void
    {
        $data = [
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email,
        ];
        if (property_exists($this, 'notify_by_email')) {
            $data['notify_by_email'] = $this->notify_by_email;
        }
        session()->put('request.patron', $data);
    }

    /**
     * Step-1 patron validation rules (shared by SFP and ILL).
     *
     * @return array<string, string>
     */
    protected function patronValidationRules(): array
    {
        $rules = [
            'barcode'    => 'required|min:5|max:20',
            'name_first' => 'required|min:1|max:100',
            'name_last'  => 'required|min:1|max:100',
            'phone'      => 'required|min:7|max:20',
            'email'      => 'nullable|email|max:255',
        ];
        if (property_exists($this, 'notify_by_email')) {
            $rules['notify_by_email'] = 'boolean';
        }

        return $rules;
    }
}

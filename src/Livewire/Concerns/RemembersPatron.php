<?php

namespace Dcplibrary\Requests\Livewire\Concerns;

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

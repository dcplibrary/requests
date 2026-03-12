<?php

namespace Dcplibrary\Requests\Livewire\Concerns;

/**
 * Shared patron session helpers for SFP and ILL forms.
 *
 * Expects the consuming component to declare public properties:
 *   string $barcode, $name_first, $name_last, $phone, $email
 */
trait RemembersPatron
{
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
        }
    }

    /**
     * Persist patron fields to the session so they survive page reloads / form switches.
     *
     * @return void
     */
    protected function savePatronToSession(): void
    {
        session()->put('request.patron', [
            'barcode'    => $this->barcode,
            'name_first' => $this->name_first,
            'name_last'  => $this->name_last,
            'phone'      => $this->phone,
            'email'      => $this->email,
        ]);
    }

    /**
     * Step-1 patron validation rules (shared by SFP and ILL).
     *
     * @return array<string, string>
     */
    protected function patronValidationRules(): array
    {
        return [
            'barcode'    => 'required|min:5|max:20',
            'name_first' => 'required|min:1|max:100',
            'name_last'  => 'required|min:1|max:100',
            'phone'      => 'required|min:7|max:20',
            'email'      => 'nullable|email|max:255',
        ];
    }
}

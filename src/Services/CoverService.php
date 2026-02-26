<?php

namespace Dcplibrary\Sfp\Services;

use Dcplibrary\Sfp\Models\Setting;

class CoverService
{
    private string $client;

    public function __construct()
    {
        $this->client = Setting::get('syndetics_client', '');
    }

    /**
     * Return a cover image URL for the given ISBN, with an optional fallback.
     * Uses Syndetics when a client ID is configured; falls back to $fallback otherwise.
     */
    public function url(?string $isbn, ?string $fallback = null): ?string
    {
        if ($isbn && $this->client) {
            return "https://www.syndetics.com/index.aspx?isbn={$isbn}&issn=/LC.JPG&client={$this->client}";
        }

        return $fallback ?: null;
    }
}

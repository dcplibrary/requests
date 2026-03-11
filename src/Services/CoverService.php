<?php

namespace Dcplibrary\Requests\Services;

use Dcplibrary\Requests\Models\Setting;

/**
 * Generates book cover image URLs.
 *
 * Prefers Syndetics when a client ID is configured in the `syndetics_client`
 * setting. Falls back to the provided fallback URL (e.g. BiblioCommons jacket
 * or ISBNdb image) when Syndetics is unavailable or no ISBN is present.
 *
 * URL format: https://www.syndetics.com/index.aspx?isbn={isbn}&issn=/LC.JPG&client={client}
 */
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

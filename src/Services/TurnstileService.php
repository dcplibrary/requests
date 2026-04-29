<?php

namespace Dcplibrary\Requests\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies Cloudflare Turnstile tokens server-side.
 *
 * Uses Laravel's HTTP client — no additional package needed.
 * Fails open when Turnstile is not configured (missing secret key) or when
 * Cloudflare is unreachable, so a network blip never locks out real patrons.
 */
class TurnstileService
{
    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Verify a Turnstile challenge token.
     *
     * @param  string       $token  The `cf-turnstile-response` value from the client.
     * @param  string|null  $ip     Optional: patron's IP address (improves Cloudflare signal).
     * @return bool  True when the challenge passed (or on fail-open conditions).
     */
    public function verify(string $token, ?string $ip = null): bool
    {
        $secretKey = config('requests.turnstile.secret_key', '');

        // Not configured — fail open so unconfigured installs are not broken.
        if (! $secretKey || ! $token) {
            return true;
        }

        $payload = [
            'secret'   => $secretKey,
            'response' => $token,
        ];

        if ($ip) {
            $payload['remoteip'] = $ip;
        }

        try {
            $response = Http::timeout(5)
                ->asForm()
                ->post(self::VERIFY_URL, $payload);

            return (bool) ($response->json('success') ?? false);
        } catch (\Throwable $e) {
            // Cloudflare unreachable — fail open so real patrons aren't blocked.
            Log::warning('Turnstile verification request failed', [
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }
}

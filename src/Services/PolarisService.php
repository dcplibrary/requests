<?php

namespace Dcplibrary\Requests\Services;

use Blashbrook\PAPIClient\PAPIClient;
use Illuminate\Support\Facades\Log;

class PolarisService
{
    /**
     * Synchronously check whether a patron barcode exists in Polaris.
     *
     * Returns:
     *   true  — barcode was found (PatronID present)
     *   false — barcode explicitly not found (API responded but no patron record)
     *   null  — API error, timeout, or Polaris not configured;
     *            callers should allow the form to proceed rather than blocking patrons
     */
    public function barcodeExists(string $barcode): ?bool
    {
        $domain   = env('PAPI_DOMAIN');
        $staff    = env('PAPI_STAFF');
        $password = env('PAPI_PASSWORD');

        // Polaris integration is optional — if not configured, let the form through.
        if (! $domain || ! $staff || ! $password) {
            return null;
        }

        try {
            // Step 1: Authenticate as staff to obtain AccessSecret.
            // Mirrors the approach used in LookupPatronInPolaris job.
            $authResponse = (new PAPIClient())
                ->method('POST')
                ->protected()
                ->uri('/authenticator/staff')
                ->params([
                    'Domain'   => $domain,
                    'Username' => $staff,
                    'Password' => $password,
                ])
                ->execRequest();

            $accessSecret = $authResponse['AccessSecret'] ?? null;

            if (! $accessSecret) {
                Log::warning('PolarisService: staff auth returned no AccessSecret', [
                    'barcode' => $barcode,
                ]);
                // Auth failure — don't block the patron
                return null;
            }

            // Step 2: GET patron basicdata by barcode.
            $data = (new PAPIClient())
                ->method('GET')
                ->patron($barcode)
                ->auth($accessSecret)
                ->uri('/basicdata')
                ->execRequest();

            $basicData = $data['PatronBasicData'] ?? $data;

            if (empty($basicData) || empty($basicData['PatronID'])) {
                return false; // Barcode not found in Polaris
            }

            return true; // Barcode found

        } catch (\Throwable $e) {
            Log::error('PolarisService: synchronous barcode check failed', [
                'barcode' => $barcode,
                'error'   => $e->getMessage(),
            ]);

            // API error — don't block the patron
            return null;
        }
    }
}

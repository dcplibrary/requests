<?php

namespace Dcplibrary\Sfp\Jobs;

use Blashbrook\PAPIClient\PAPIClient;
use Dcplibrary\Sfp\Models\Patron;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class LookupPatronInPolaris implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    public function __construct(public readonly int $patronId) {}

    public function handle(PAPIClient $papiclient): void
    {
        $patron = Patron::find($this->patronId);

        if (! $patron || $patron->polaris_lookup_attempted) {
            return;
        }

        try {
            // Step 1: Authenticate as staff to obtain an AccessSecret.
            // The AccessSecret is required as X-PAPI-AccessToken on patron endpoints.
            // protectedURI has no trailing slash, so uri needs a leading slash.
            // getPolarisSettings() auto-prepends LogonWorkstationID and PatronBranchID.
            $authResponse = app(PAPIClient::class)
                ->method('POST')
                ->protected()
                ->uri('/authenticator/staff')
                ->params([
                    'Domain'   => env('PAPI_DOMAIN'),
                    'Username' => env('PAPI_STAFF'),
                    'Password' => env('PAPI_PASSWORD'),
                ])
                ->execRequest();

            $accessSecret = $authResponse['AccessSecret'] ?? null;

            if (! $accessSecret) {
                Log::warning('Polaris staff auth returned no AccessSecret', [
                    'patron_id' => $this->patronId,
                    'response'  => $authResponse,
                ]);
                $patron->markPolarisNotFound();
                return;
            }

            // Step 2: GET patron basicdata by barcode via Polaris public PAPI.
            // execRequest() returns an array directly (not a Response object).
            // URI builds as: {publicURI}patron/{barcode}/basicdata
            $data = app(PAPIClient::class)
                ->method('GET')
                ->patron($patron->barcode)
                ->auth($accessSecret)
                ->uri('/basicdata')
                ->execRequest();

            // Polaris wraps the payload in PatronBasicData
            $basicData = $data['PatronBasicData'] ?? $data;

            if (empty($basicData) || empty($basicData['PatronID'])) {
                $patron->markPolarisNotFound();
                return;
            }

            // Step 3: Fetch full patron registration via the protected (staff) endpoint.
            // protectedURI has no trailing slash, so uri needs a leading slash.
            $polarisPatronId = $basicData['PatronID'];
            $reg = app(PAPIClient::class)
                ->method('GET')
                ->protected()
                ->uri("/patron/{$polarisPatronId}")
                ->execRequest();

            $regData = $reg['PatronRegistrationData'] ?? $reg;

            $patron->applyPolarisData([
                'PatronID'      => $polarisPatronId,
                'PatronCodeID'  => $basicData['PatronCodeID'] ?? null,
                'NameFirst'     => $regData['NameFirst'] ?? $basicData['NameFirst'] ?? null,
                'NameLast'      => $regData['NameLast'] ?? $basicData['NameLast'] ?? null,
                'PhoneVoice1'   => $regData['PhoneVoice1'] ?? null,
                'EmailAddress'  => $regData['EmailAddress'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('Polaris patron lookup failed', [
                'patron_id' => $this->patronId,
                'barcode'   => $patron->barcode,
                'error'     => $e->getMessage(),
            ]);

            // Don't rethrow — a Polaris failure should not block the patron's submission
            $patron->update([
                'polaris_lookup_attempted' => true,
                'polaris_lookup_at' => now(),
            ]);
        }
    }
}

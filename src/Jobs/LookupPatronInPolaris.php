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
            // GET patron basicdata by barcode via Polaris public PAPI.
            // execRequest() returns an array directly (not a Response object).
            // URI builds as: {publicURI}patron/{barcode}/basicdata
            $data = $papiclient
                ->method('GET')
                ->patron($patron->barcode)
                ->uri('/basicdata')
                ->execRequest();

            // Polaris wraps the payload in PatronBasicData
            $basicData = $data['PatronBasicData'] ?? $data;

            if (empty($basicData) || empty($basicData['PatronID'])) {
                $patron->markPolarisNotFound();
                return;
            }

            // Fetch full patron registration via the protected (staff) endpoint.
            // Use a fresh client instance to ensure protected/public state doesn't bleed.
            $polarisPatronId = $basicData['PatronID'];
            $reg = app(PAPIClient::class)
                ->method('GET')
                ->protected()
                ->uri("patron/{$polarisPatronId}")
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

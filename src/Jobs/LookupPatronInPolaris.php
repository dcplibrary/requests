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
            // GET patron basicdata by barcode via Polaris PAPI
            $response = $papiclient
                ->method('GET')
                ->patron($patron->barcode)
                ->uri('basicdata')
                ->execRequest();

            $body = $response->json() ?? [];

            // Polaris returns PatronBasicData wrapper
            $data = $body['PatronBasicData'] ?? $body;

            if (empty($data) || empty($data['PatronID'])) {
                $patron->markPolarisNotFound();
                return;
            }

            // Fetch PatronRegistration fields
            $patronId = $data['PatronID'];
            $regResponse = $papiclient
                ->method('GET')
                ->protected()
                ->uri("patron/{$patronId}")
                ->execRequest();

            $regBody = $regResponse->json() ?? [];
            $reg = $regBody['PatronRegistrationData'] ?? $regBody;

            $patron->applyPolarisData([
                'PatronID'      => $patronId,
                'PatronCodeID'  => $data['PatronCodeID'] ?? null,
                'NameFirst'     => $reg['NameFirst'] ?? $data['NameFirst'] ?? null,
                'NameLast'      => $reg['NameLast'] ?? $data['NameLast'] ?? null,
                'PhoneVoice1'   => $reg['PhoneVoice1'] ?? null,
                'EmailAddress'  => $reg['EmailAddress'] ?? null,
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

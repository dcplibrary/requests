<?php

namespace Dcplibrary\Requests\Jobs;

use Blashbrook\PAPIClient\PAPIClient;
use Dcplibrary\Requests\Models\Patron;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async Polaris PAPI patron basicdata lookup after submission; updates patron match flags.
 *
 * Dispatched with the patron's library barcode — the only identifier Polaris
 * uses for lookup. The local DB patron record is found by barcode, not by the
 * local primary key, because patron_id is a value that comes FROM Polaris, not
 * something we send to it.
 */
class LookupPatronInPolaris implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * @param string $barcode The patron's library barcode used for Polaris lookup.
     */
    public function __construct(public readonly string $barcode) {}

    public function handle(PAPIClient $papiclient): void
    {
        $patron = Patron::where('barcode', $this->barcode)->first();

        if (! $patron || $patron->polaris_lookup_attempted) {
            return;
        }

        try {
            // PAPIClient is registered as a singleton, so we must use `new` to get
            // a fresh instance for each call — otherwise state (protected, uri,
            // params, accessSecret) from a prior call bleeds into subsequent ones.

            // Step 1: Authenticate as staff to obtain an AccessSecret.
            // The AccessSecret is required as X-PAPI-AccessToken on patron endpoints.
            // 'authenticator/staff' has no leading slash; protectedURI already has a
            // trailing slash, so the combined URL is correct with no double-slash.
            $authResponse = (new PAPIClient())
                ->method('POST')
                ->protected()
                ->uri('authenticator/staff')
                ->params([
                    'Domain'   => config('requests.polaris.domain', ''),
                    'Username' => config('requests.polaris.staff', ''),
                    'Password' => config('requests.polaris.password', ''),
                ])
                ->execRequest();

            $accessSecret = $authResponse['AccessSecret'] ?? null;

            if (! $accessSecret) {
                Log::warning('Polaris staff auth returned no AccessSecret', [
                    'barcode'  => $this->barcode,
                    'response' => $authResponse,
                ]);
                // Staff auth failed — mark as attempted but don't mark patron as "not found"
                // since we never actually checked if the patron exists in Polaris.
                $patron->update([
                    'polaris_lookup_attempted' => true,
                    'polaris_lookup_at' => now(),
                ]);
                return;
            }

            // Step 2: GET patron basicdata by barcode via Polaris public PAPI.
            // execRequest() builds: {publicURI}patron/{barcode} + uri
            // The leading slash on '/basicdata' is required to produce the correct
            // path: patron/{barcode}/basicdata — without it the slash is missing.
            $data = (new PAPIClient())
                ->method('GET')
                ->patron($this->barcode)
                ->auth($accessSecret)
                ->uri('/basicdata')
                ->execRequest();

            // Polaris wraps the payload in PatronBasicData
            $basicData = $data['PatronBasicData'] ?? $data;

            if (empty($basicData) || empty($basicData['PatronID'])) {
                $patron->markPolarisNotFound();
                return;
            }

            // basicdata returns all the fields we need — no second call required.
            // Note: the phone field is PhoneNumber in basicdata (not PhoneVoice1).
            $patron->applyPolarisData([
                'PatronID'      => $basicData['PatronID'],
                'PatronCodeID'  => $basicData['PatronCodeID'] ?? null,
                'NameFirst'     => $basicData['NameFirst'] ?? null,
                'NameLast'      => $basicData['NameLast'] ?? null,
                'PhoneVoice1'   => $basicData['PhoneNumber'] ?? $basicData['CellPhone'] ?? null,
                'EmailAddress'  => $basicData['EmailAddress'] ?? null,
            ]);

        } catch (\Throwable $e) {
            Log::error('Polaris patron lookup failed', [
                'barcode' => $this->barcode,
                'error'   => $e->getMessage(),
            ]);

            // Don't rethrow — a Polaris failure should not block the patron's submission
            $patron->update([
                'polaris_lookup_attempted' => true,
                'polaris_lookup_at' => now(),
            ]);
        }
    }
}

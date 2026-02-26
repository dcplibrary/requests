<?php

namespace Dcplibrary\Sfp\Services;

use Dcplibrary\Sfp\Jobs\LookupPatronInPolaris;
use Dcplibrary\Sfp\Models\Patron;

/**
 * Creates or retrieves patrons during form submission.
 *
 * On first encounter (new barcode), creates the patron record and dispatches
 * `LookupPatronInPolaris` to the queue so validation against the ILS happens
 * asynchronously after the patron's form is submitted.
 */
class PatronService
{
    /**
     * Find or create a patron by barcode.
     * If new, queues a Polaris lookup job.
     * Returns the patron and whether it was newly created.
     */
    public function findOrCreate(array $data): array
    {
        $existing = Patron::where('barcode', $data['barcode'])->first();

        if ($existing) {
            return ['patron' => $existing, 'created' => false];
        }

        $patron = Patron::create([
            'barcode'    => $data['barcode'],
            'name_first' => $data['name_first'],
            'name_last'  => $data['name_last'],
            'phone'      => $data['phone'],
            'email'      => $data['email'] ?? null,
        ]);

        // Queue Polaris lookup — runs after submission
        LookupPatronInPolaris::dispatch($patron->id);

        return ['patron' => $patron, 'created' => true];
    }
}

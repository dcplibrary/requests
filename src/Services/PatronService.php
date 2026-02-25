<?php

namespace Dcplibrary\Sfp\Services;

use Dcplibrary\Sfp\Jobs\LookupPatronInPolaris;
use Dcplibrary\Sfp\Models\Patron;

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

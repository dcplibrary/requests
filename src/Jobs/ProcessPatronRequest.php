<?php

namespace Dcplibrary\Requests\Jobs;

use Dcplibrary\Requests\Models\FieldOption;
use Dcplibrary\Requests\Models\PatronRequest;
use Dcplibrary\Requests\Services\BibliocommonsService;
use Dcplibrary\Requests\Services\IsbnDbService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async Bibliocommons catalog + ISBNdb search after a patron request is created.
 */
class ProcessPatronRequest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $requestId) {}

    public function handle(BibliocommonsService $bibliocommons, IsbnDbService $isbndb): void
    {
        $request = PatronRequest::with(['fieldValues.field'])->find($this->requestId);

        if (! $request) {
            return;
        }

        // 1. Catalog search — audience lives in request_field_values + field_options.metadata
        $audienceSlug  = $request->fieldValue('audience');
        $audienceValue = 'adult';
        if ($audienceSlug) {
            $audienceOption = FieldOption::query()
                ->whereHas('field', fn ($q) => $q->where('key', 'audience'))
                ->where('slug', $audienceSlug)
                ->first();
            $audienceValue = $audienceOption?->meta('bibliocommons_value', 'adult') ?? 'adult';
        }

        $catalogResult = $bibliocommons->search(
            $request->submitted_title,
            $request->submitted_author,
            $audienceValue,
            $request->submitted_publish_date
        );

        $request->update([
            'catalog_searched'     => true,
            'catalog_result_count' => $catalogResult['total'],
        ]);

        // If catalog found results, we return — patron interaction happens in-form (Livewire)
        // The job stores results; Livewire reads them via broadcasting or polling
        if ($catalogResult['total'] > 0) {
            // Store results in cache for Livewire to pick up
            cache()->put(
                "requests_catalog_{$this->requestId}",
                $catalogResult,
                now()->addMinutes(30)
            );
            return;
        }

        // 2. ISBNdb search (only if catalog found nothing)
        $isbndbResult = $isbndb->search(
            $request->submitted_title,
            $request->submitted_author
        );

        $request->update([
            'isbndb_searched'     => true,
            'isbndb_result_count' => $isbndbResult['total'],
        ]);

        if ($isbndbResult['total'] > 0) {
            cache()->put(
                "requests_isbndb_{$this->requestId}",
                $isbndbResult,
                now()->addMinutes(30)
            );
        }

        Log::info('Patron request processed', [
            'request_id'    => $this->requestId,
            'catalog_hits'  => $catalogResult['total'],
            'isbndb_hits'   => $isbndbResult['total'],
        ]);
    }
}

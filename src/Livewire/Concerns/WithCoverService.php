<?php

namespace Dcplibrary\Requests\Livewire\Concerns;

use Dcplibrary\Requests\Services\CoverService;

/**
 * Decorates search results with a cover_url from CoverService.
 *
 * Handles both 'catalog' (isbns[] + jacket fallback) and 'isbndb'
 * (isbn13/isbn + image fallback) result shapes.
 */
trait WithCoverService
{
    /**
     * Decorate search results with a cover_url.
     *
     * @param  array<int, array>  $results
     * @param  string             $source   'catalog' or 'isbndb'
     * @return array<int, array>
     */
    protected function withCovers(array $results, string $source): array
    {
        $covers = app(CoverService::class);

        return array_map(function (array $result) use ($covers, $source) {
            if ($source === 'catalog') {
                $isbn     = $result['isbns'][0] ?? null;
                $fallback = $result['jacket'] ?? null;
            } else {
                $isbn     = $result['isbn13'] ?? $result['isbn'] ?? null;
                $fallback = $result['image'] ?? null;
            }

            $result['cover_url'] = $covers->url($isbn, $fallback);

            return $result;
        }, $results);
    }
}

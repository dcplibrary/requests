<?php

namespace Dcplibrary\Requests\Services;

use Dcplibrary\Requests\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for searching the BiblioCommons catalog.
 *
 * Uses the internal BiblioCommons gateway JSON API — the same endpoint the
 * catalog's React SPA calls via XHR. Results are capped at 5 per search.
 *
 * The library slug is read from the `catalog_library_slug` setting at runtime,
 * allowing it to be changed without a deploy.
 */
class BibliocommonsService
{
    /**
     * The Bibliocommons library slug (subdomain).
     * Configurable via the 'catalog_library_slug' setting.
     */
    private function librarySlug(): string
    {
        return Setting::get('catalog_library_slug', 'dcpl');
    }

    /**
     * Search the Bibliocommons catalog via the internal JSON API and return parsed results.
     *
     * @return array{results: array, total: int, url: string}
     */
    public function search(string $title, string $author, string $audienceBiblioValue, ?string $year = null): array
    {
        $slug  = $this->librarySlug();
        $query = $this->buildQuery($title, $author, $audienceBiblioValue, $year);

        // The gateway JSON API is what Bibliocommons' own SPA uses internally.
        // The v2/search page is a React app that loads results via XHR — plain HTTP
        // fetches of that page return an empty shell with no result data.
        $apiUrl = "https://gateway.bibliocommons.com/v2/libraries/{$slug}/bibs/search";

        // The catalog browse URL (for humans) — built from the same query
        $browseUrl = "https://{$slug}.bibliocommons.com/v2/search?query=" . urlencode($query) . '&searchType=bl';

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; RequestsBot/1.0)',
                    'Accept'     => 'application/json',
                ])
                ->get($apiUrl, [
                    'query'      => $query,
                    'searchType' => 'bl',
                    'suppress'   => 'true',
                ]);

            if (! $response->ok()) {
                Log::warning('Bibliocommons API search failed', [
                    'status' => $response->status(),
                    'url'    => $apiUrl,
                    'query'  => $query,
                ]);
                return ['results' => [], 'total' => 0, 'url' => $browseUrl];
            }

            $data    = $response->json();
            $results = $this->parseApiResponse($data, $slug);

            return array_merge($results, ['url' => $browseUrl]);

        } catch (\Throwable $e) {
            Log::error('Bibliocommons API search exception', ['error' => $e->getMessage()]);
            return ['results' => [], 'total' => 0, 'url' => $browseUrl];
        }
    }

    /**
     * Extract the last name from an author string for use in the contributor field.
     *
     * Bibliocommons stores authors in "Last, First" MARC format. Searching by full
     * name ("Frieda McFadden") fails when the catalog has a different first-name
     * spelling or ordering (e.g. "McFadden, Freida"). Using only the last name makes
     * the contributor filter resilient to first-name typos in either direction.
     *
     * Handles:
     *   "Alice Feeney"       → "Feeney"
     *   "McFadden, Frieda"   → "McFadden"
     *   "Feeney"             → "Feeney"
     */
    private function extractLastName(string $author): string
    {
        $author = trim($author);

        // Already in "Last, First" format
        if (str_contains($author, ',')) {
            return trim(explode(',', $author)[0]);
        }

        // "First Last" — take the last word
        $parts = preg_split('/\s+/', $author);
        return end($parts) ?: $author;
    }

    /**
     * Build the Bibliocommons boolean search query string.
     */
    private function buildQuery(string $title, string $author, string $audience, ?string $year): string
    {
        $lastName = $this->extractLastName($author);

        $parts = [
            'title:(' . $title . ')',
            'contributor:(' . $lastName . ')',
        ];

        if ($audience) {
            $parts[] = 'audience:"' . $audience . '"';
        }

        // Only filter by year for recent titles. For older books the patron
        // enters the original publication year, but the catalog holds later
        // editions/reprints — a tight year window would miss them entirely.
        if ($year && (int) $year >= (now()->year - 2)) {
            $yearFrom = max(1, (int) $year - 1);
            $yearTo   = (int) $year + 1;
            $parts[]  = "pubyear:[{$yearFrom} TO {$yearTo}]";
        }

        return implode(' ', $parts);
    }

    /**
     * Parse the Bibliocommons gateway JSON API response.
     *
     * @return array{results: array, total: int}
     */
    private function parseApiResponse(array $data, string $slug): array
    {
        $catalogSearch = $data['catalogSearch'] ?? [];
        $total         = $catalogSearch['pagination']['count'] ?? 0;

        // Ordered result list from the catalogSearch
        $orderedResults = $catalogSearch['results'] ?? [];

        // Bib entities keyed by ID
        $bibEntities = $data['entities']['bibs'] ?? [];

        $results = [];

        foreach ($orderedResults as $resultRow) {
            $bibId = $resultRow['representative'] ?? null;
            if (! $bibId || ! isset($bibEntities[$bibId])) {
                continue;
            }

            $bib  = $bibEntities[$bibId];
            $info = $bib['briefInfo'] ?? [];

            $author = '';
            if (! empty($info['authors']) && is_array($info['authors'])) {
                $author = implode(', ', $info['authors']);
            }

            $results[] = [
                'bib_id'      => $bibId,
                'title'       => $info['title'] ?? '',
                'subtitle'    => $info['subtitle'] ?? '',
                'author'      => $author,
                'format'      => $info['format'] ?? '',
                'year'        => $info['publicationDate'] ?? '',
                'edition'     => $info['edition'] ?? '',
                'isbns'       => $info['isbns'] ?? [],
                'jacket'      => $info['jacket']['medium'] ?? null,
                'catalog_url' => "https://{$slug}.bibliocommons.com/v2/record/{$bibId}",
            ];

            if (count($results) >= 5) {
                break;
            }
        }

        return ['results' => $results, 'total' => $total];
    }
}

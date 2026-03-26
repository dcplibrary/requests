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
     * Tries progressively broader strategies when the catalog returns no hits — e.g. unquoted
     * `[` / `]` in titles break Lucene range parsing; audience facets can miss juvenile records.
     *
     * @return array{results: array, total: int, url: string}
     */
    public function search(string $title, string $author, string $audienceBiblioValue, ?string $year = null): array
    {
        $slug = $this->librarySlug();

        foreach ($this->catalogSearchStrategies($title, $audienceBiblioValue) as $params) {
            $query = $this->buildQuery(
                $params['title'],
                $author,
                $params['audience'],
                $year,
                $params['includeContributor'] ?? true
            );
            $browseUrl = "https://{$slug}.bibliocommons.com/v2/search?query=" . urlencode($query) . '&searchType=bl';

            $parsed = $this->performGatewaySearch($slug, $query, 'bl');
            if (count($parsed['results']) > 0) {
                return array_merge($parsed, ['url' => $browseUrl]);
            }
        }

        // Boolean search often misses bibs the website finds (punctuation, tokenization, audience).
        // Mirror the catalog omnibox: plain keywords + searchType smart/keyword (varies by site build).
        foreach ($this->catalogSmartSearchQueries($title, $author) as $smartQuery) {
            foreach (['smart', 'keyword'] as $smartType) {
                $browseUrl = "https://{$slug}.bibliocommons.com/v2/search?query=" . urlencode($smartQuery) . '&searchType=' . $smartType;
                $parsed    = $this->performGatewaySearch($slug, $smartQuery, $smartType);
                if (count($parsed['results']) > 0) {
                    return array_merge($parsed, ['url' => $browseUrl]);
                }
            }
        }

        // Last attempt produced no rows — still return its browse URL for staff debugging
        $fallbackQuery = $this->buildQuery(
            $this->normalizeTitleWhitespace($title),
            $author,
            $audienceBiblioValue,
            $year,
            true
        );
        $fallbackUrl = "https://{$slug}.bibliocommons.com/v2/search?query=" . urlencode($fallbackQuery) . '&searchType=bl';

        return ['results' => [], 'total' => 0, 'url' => $fallbackUrl];
    }

    /**
     * Plain-text queries for searchType=smart (same mode as the patron-facing catalog search box).
     *
     * @return list<string>
     */
    private function catalogSmartSearchQueries(string $title, string $author): array
    {
        $last    = $this->extractLastName($author);
        $full    = $this->normalizeTitleWhitespace($title);
        $simple  = $this->simplifyTitleForCatalog($title);
        $primary = $this->extractPrimaryTitleToken($full);
        $loose   = $this->looseKeywordsFromTitle($full);

        $candidates = array_filter([
            $last !== '' ? trim($full . ' ' . $last) : $full,
            $last !== '' && $simple !== '' && strcasecmp($simple, $full) !== 0 ? trim($simple . ' ' . $last) : '',
            $last !== '' && $primary !== '' ? trim($primary . ' ' . $last) : '',
            $last !== '' && $loose !== '' && strcasecmp($loose, $full) !== 0 ? trim($loose . ' ' . $last) : '',
            $full,
            $simple !== '' && strcasecmp($simple, $full) !== 0 ? $simple : '',
            $primary !== '' ? $primary : '',
            $loose !== '' ? $loose : '',
        ]);

        $out   = [];
        $seenK = [];
        foreach ($candidates as $q) {
            $q = $this->normalizeTitleWhitespace($q);
            if ($q === '' || isset($seenK[$q])) {
                continue;
            }
            $seenK[$q] = true;
            $out[]     = $q;
        }

        return $out;
    }

    /**
     * Title with punctuation collapsed to spaces — closer to how patrons type in a keyword box.
     */
    private function looseKeywordsFromTitle(string $title): string
    {
        $t = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $title) ?? $title;

        return $this->normalizeTitleWhitespace($t);
    }

    /**
     * Ordered strategies: title+contributor first, then broader (no audience, shorter title,
     * primary word). Finally title-only queries — contributor: sometimes fails to match catalog
     * tokenization even when the title is exact.
     *
     * @return list<array{title: string, audience: string, includeContributor?: bool}>
     */
    private function catalogSearchStrategies(string $title, string $audience): array
    {
        $full    = $this->normalizeTitleWhitespace($title);
        $simple  = $this->simplifyTitleForCatalog($title);
        $primary = $this->extractPrimaryTitleToken($full);

        $out   = [];
        $seenK = [];

        $push = function (string $t, string $aud, bool $includeContributor = true) use (&$out, &$seenK): void {
            $t = $this->normalizeTitleWhitespace($t);
            if ($t === '') {
                return;
            }
            $k = $t . "\0" . $aud . "\0" . ($includeContributor ? '1' : '0');
            if (isset($seenK[$k])) {
                return;
            }
            $seenK[$k] = true;
            $out[]     = [
                'title'               => $t,
                'audience'            => $aud,
                'includeContributor'  => $includeContributor,
            ];
        };

        $push($full, $audience, true);
        if ($audience !== '') {
            $push($full, '', true);
        }

        if ($simple !== '' && strcasecmp($simple, $full) !== 0) {
            $push($simple, $audience, true);
            if ($audience !== '') {
                $push($simple, '', true);
            }
        }

        if ($primary !== '' && strcasecmp($primary, $full) !== 0 && strcasecmp($primary, $simple) !== 0) {
            $push($primary, $audience, true);
            if ($audience !== '') {
                $push($primary, '', true);
            }
        }

        // Title phrase only (no contributor:) — last resort for stubborn matches
        foreach ([$full, $simple, $primary] as $t) {
            $t = $this->normalizeTitleWhitespace((string) $t);
            if ($t === '') {
                continue;
            }
            $push($t, $audience, false);
            if ($audience !== '') {
                $push($t, '', false);
            }
        }

        return $out;
    }

    private function normalizeTitleWhitespace(string $title): string
    {
        return trim(preg_replace('/\s+/u', ' ', $title) ?? '');
    }

    /**
     * Drop bracketed segments (e.g. "[Volume 1]") that confuse boolean search and rarely match catalog tokens.
     */
    private function simplifyTitleForCatalog(string $title): string
    {
        $t = $this->normalizeTitleWhitespace($title);
        $t = preg_replace('/\s*\[[^\]]*\]\s*,?\s*/u', ' ', $t) ?? $t;

        return $this->normalizeTitleWhitespace($t);
    }

    /**
     * First word-like token (letters/digits), for a last-resort keyword-style title clause.
     */
    private function extractPrimaryTitleToken(string $title): string
    {
        if (preg_match('/[\p{L}][\p{L}\p{N}]*/u', $title, $m) === 1) {
            return $m[0];
        }

        return '';
    }

    /**
     * Wrap text as a Lucene phrase literal inside field parentheses — brackets/punctuation are literal; escape \ and ".
     */
    private function quoteLucenePhrase(string $value): string
    {
        $value = str_replace(['\\', '"'], ['\\\\', '\\"'], trim($value));

        return '"' . $value . '"';
    }

    /**
     * @return array{results: array, total: int}
     */
    private function performGatewaySearch(string $slug, string $query, string $searchType = 'bl'): array
    {
        $apiUrl = "https://gateway.bibliocommons.com/v2/libraries/{$slug}/bibs/search";

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent'      => 'Mozilla/5.0 (compatible; RequestsBot/1.0)',
                    'Accept'          => 'application/json',
                    'Accept-Language' => 'en-US,en;q=0.9',
                    'Referer'         => "https://{$slug}.bibliocommons.com/",
                    'Origin'          => "https://{$slug}.bibliocommons.com",
                ])
                ->get($apiUrl, [
                    'query'      => $query,
                    'searchType' => $searchType,
                    'suppress'   => 'true',
                ]);

            if (! $response->ok()) {
                Log::warning('Bibliocommons API search failed', [
                    'status' => $response->status(),
                    'url'    => $apiUrl,
                    'query'  => $query,
                ]);

                return ['results' => [], 'total' => 0];
            }

            return $this->parseApiResponse($response->json() ?? [], $slug);

        } catch (\Throwable $e) {
            Log::error('Bibliocommons API search exception', ['error' => $e->getMessage()]);

            return ['results' => [], 'total' => 0];
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
     *
     * Uses {@code title:"..."} / {@code contributor:"..."} (not {@code field:("...")}) — the
     * gateway parser appears to match the catalog UI more reliably for phrase searches.
     */
    private function buildQuery(string $title, string $author, string $audience, ?string $year, bool $includeContributor = true): string
    {
        $lastName = $this->extractLastName($author);

        // Phrase-quoted so `[` `]` `,` in titles are literal, not Lucene range operators.
        $parts = [
            'title:' . $this->quoteLucenePhrase($this->normalizeTitleWhitespace($title)),
        ];

        if ($includeContributor && $lastName !== '') {
            $parts[] = 'contributor:' . $this->quoteLucenePhrase($lastName);
        }

        if ($audience !== '') {
            $parts[] = 'audience:"' . str_replace('"', '\\"', $audience) . '"';
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

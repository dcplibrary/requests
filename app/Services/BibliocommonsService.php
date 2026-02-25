<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BibliocommonsService
{
    /**
     * Search the Bibliocommons catalog and return parsed results.
     *
     * @return array{results: array, total: int, url: string}
     */
    public function search(string $title, string $author, string $audienceBiblioValue, ?string $year = null): array
    {
        $url = $this->buildUrl($title, $author, $audienceBiblioValue, $year);

        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; DCPLSfpBot/1.0)'])
                ->get($url);

            if (! $response->ok()) {
                Log::warning('Bibliocommons search failed', ['status' => $response->status(), 'url' => $url]);
                return ['results' => [], 'total' => 0, 'url' => $url];
            }

            return array_merge($this->parseHtml($response->body()), ['url' => $url]);

        } catch (\Throwable $e) {
            Log::error('Bibliocommons search exception', ['error' => $e->getMessage()]);
            return ['results' => [], 'total' => 0, 'url' => $url];
        }
    }

    private function buildUrl(string $title, string $author, string $audience, ?string $year): string
    {
        $template = Setting::get(
            'catalog_search_url_template',
            'https://dcpl.bibliocommons.com/v2/search?custom_edit=false&query=(title%3A({title})%20AND%20contributor%3A({author})%20)%20audience%3A%22{audience}%22%20pubyear%3A%5B{year_from}%20TO%20{year_to}%5D&searchType=bl&suppress=true'
        );

        $yearFrom = $year ? max(1, (int) $year - 1) : 1800;
        $yearTo   = $year ? (int) $year + 1 : now()->year;

        return str_replace(
            ['{title}', '{author}', '{audience}', '{year_from}', '{year_to}'],
            [urlencode($title), urlencode($author), $audience, $yearFrom, $yearTo],
            $template
        );
    }

    /**
     * Parse Bibliocommons HTML response to extract result items.
     * Uses DOMDocument / regex fallback since there's no public API.
     *
     * Returns array of result objects with title, author, year, format, bib_id.
     */
    private function parseHtml(string $html): array
    {
        $results = [];
        $total   = 0;

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // Total result count
        $countNodes = $xpath->query('//*[contains(@class,"results-count")]');
        if ($countNodes->length > 0) {
            preg_match('/\d+/', $countNodes->item(0)->textContent, $matches);
            $total = isset($matches[0]) ? (int) $matches[0] : 0;
        }

        // Individual result items — Bibliocommons uses data-bib-id on list items
        $items = $xpath->query('//*[@data-bib-id]');

        foreach ($items as $item) {
            $bibId = $item->getAttribute('data-bib-id');

            // Title
            $titleNode = $xpath->query('.//*[contains(@class,"title-content")]', $item);
            $itemTitle = $titleNode->length > 0 ? trim($titleNode->item(0)->textContent) : '';

            // Author/contributor
            $authorNode = $xpath->query('.//*[contains(@class,"author-link")]', $item);
            $itemAuthor = $authorNode->length > 0 ? trim($authorNode->item(0)->textContent) : '';

            // Format
            $formatNode = $xpath->query('.//*[contains(@class,"format-field")]', $item);
            $itemFormat = $formatNode->length > 0 ? trim($formatNode->item(0)->textContent) : '';

            // Publication year
            $pubNode = $xpath->query('.//*[contains(@class,"pub-date")]', $item);
            $itemYear = '';
            if ($pubNode->length > 0) {
                preg_match('/\d{4}/', $pubNode->item(0)->textContent, $yearMatches);
                $itemYear = $yearMatches[0] ?? '';
            }

            if ($bibId && $itemTitle) {
                $results[] = [
                    'bib_id' => $bibId,
                    'title'  => $itemTitle,
                    'author' => $itemAuthor,
                    'format' => $itemFormat,
                    'year'   => $itemYear,
                    'catalog_url' => "https://dcpl.bibliocommons.com/v2/record/{$bibId}",
                ];
            }
        }

        // Fallback: if xpath parsing yields nothing, try regex for bib IDs
        if (empty($results) && $total === 0) {
            preg_match_all('/data-bib-id="([^"]+)"/', $html, $bibMatches);
            $total = count($bibMatches[1]);
            // Results will be empty but total will indicate hits
        }

        return ['results' => array_slice($results, 0, 5), 'total' => $total];
    }
}

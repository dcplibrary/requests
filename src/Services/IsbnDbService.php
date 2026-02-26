<?php

namespace Dcplibrary\Sfp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IsbnDbService
{
    private string $apiKey;
    private string $baseUrl = 'https://api2.isbndb.com';

    public function __construct()
    {
        $this->apiKey = config('sfp.isbndb.key', '');
    }

    /**
     * Search ISBNdb by title and author.
     *
     * @return array{results: array, total: int}
     */
    public function search(string $title, string $author): array
    {
        if (empty($this->apiKey)) {
            Log::warning('ISBNdb API key not configured.');
            return ['results' => [], 'total' => 0];
        }

        try {
            // Strip punctuation from the title before querying — ISBNdb searches
            // the literal string, so "Worst.person.ever" returns nothing whereas
            // "Worst person ever" returns the correct results.
            $searchTitle = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-Z0-9\s]/u', ' ', $title)));

            // Search by title first; author narrows down in result filtering
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->get("{$this->baseUrl}/books/" . urlencode($searchTitle), [
                    'language' => 'en',
                    'pageSize' => 20,
                ]);

            if (! $response->ok()) {
                Log::warning('ISBNdb search failed', ['status' => $response->status()]);
                return ['results' => [], 'total' => 0];
            }

            $data = $response->json();
            $books = $data['books'] ?? [];
            $total = $data['total'] ?? count($books);

            // Filter by author last name — more resilient than first word since
            // ISBNdb (like Bibliocommons) may spell first names differently
            // (e.g. "Freida" vs "Frieda"). Last name is consistent.
            if ($author) {
                $lastName = strtolower($this->extractLastName($author));
                $books = array_filter($books, function ($book) use ($lastName) {
                    $bookAuthors = strtolower(implode(' ', $book['authors'] ?? []));
                    return str_contains($bookAuthors, $lastName);
                });
            }

            $results = array_values(array_map(fn ($book) => $this->normalizeBook($book), $books));

            return ['results' => array_slice($results, 0, 5), 'total' => $total];

        } catch (\Throwable $e) {
            Log::error('ISBNdb search exception', ['error' => $e->getMessage()]);
            return ['results' => [], 'total' => 0];
        }
    }

    /**
     * Extract the last name from an author string.
     * Handles "First Last" and "Last, First" (MARC) formats.
     */
    private function extractLastName(string $author): string
    {
        $author = trim($author);
        if (str_contains($author, ',')) {
            return trim(explode(',', $author)[0]);
        }
        $parts = preg_split('/\s+/', $author);
        return end($parts) ?: $author;
    }

    /**
     * Normalize an ISBNdb book record to a consistent shape.
     */
    private function normalizeBook(array $book): array
    {
        return [
            'isbn'             => $book['isbn'] ?? null,
            'isbn13'           => $book['isbn13'] ?? null,
            'title'            => $book['title'] ?? '',
            'title_long'       => $book['title_long'] ?? '',
            'authors'          => $book['authors'] ?? [],
            'author_string'    => implode(', ', $book['authors'] ?? []),
            'publisher'        => $book['publisher'] ?? null,
            'publish_date'     => $book['date_published'] ?? null,
            'edition'          => $book['edition'] ?? null,
            'overview'         => $book['overview'] ?? null,
            'image'            => $book['image'] ?? null,
            'binding'          => $book['binding'] ?? null,
        ];
    }
}

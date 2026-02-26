<?php

namespace Dcplibrary\Sfp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the ISBNdb v2 REST API.
 *
 * Used as a fallback enrichment source when the catalog search returns no
 * results. Performs a two-stage search:
 *  1. Search by title, filter results by author last name.
 *  2. If stage 1 returns nothing, search by author last name and filter by
 *     significant title words (stopword-aware). This handles cases where a
 *     generic title (e.g. "The Bible Tells Me So") buries the right book
 *     beyond the first page of results.
 *
 * The API key is read from `config('sfp.isbndb.key')`.
 */
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
            $lastName    = $author ? strtolower($this->extractLastName($author)) : '';
            $searchTitle = trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-Z0-9\s]/u', ' ', $title)));

            // Primary: search by title, filter results by author last name.
            $books = $this->fetchByTitle($searchTitle, $lastName);

            // Fallback: if the title query returned rows but none matched the author,
            // the book is likely buried beyond our page window (e.g. a generic title
            // like "The Bible Tells Me So" with 200+ results). Search by author last
            // name instead and filter those results by title words.
            if (empty($books) && $lastName) {
                $books = $this->fetchByAuthor($lastName, $searchTitle);
            }

            $results = array_values(array_map(fn ($book) => $this->normalizeBook($book), $books));

            return ['results' => array_slice($results, 0, 5), 'total' => count($results)];

        } catch (\Throwable $e) {
            Log::error('ISBNdb search exception', ['error' => $e->getMessage()]);
            return ['results' => [], 'total' => 0];
        }
    }

    /**
     * Search ISBNdb by title, optionally filtering results by author last name.
     */
    private function fetchByTitle(string $searchTitle, string $lastName): array
    {
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
            Log::warning('ISBNdb title search failed', ['status' => $response->status()]);
            return [];
        }

        $books = $response->json()['books'] ?? [];

        if ($lastName) {
            $books = array_values(array_filter($books, function ($book) use ($lastName) {
                return str_contains(strtolower(implode(' ', $book['authors'] ?? [])), $lastName);
            }));
        }

        return $books;
    }

    /**
     * Search ISBNdb by author last name, filtering results by title keywords.
     * Used as a fallback when the title is too generic to surface the right book.
     */
    private function fetchByAuthor(string $lastName, string $searchTitle): array
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => $this->apiKey,
                'Content-Type'  => 'application/json',
            ])
            ->get("{$this->baseUrl}/books/" . urlencode($lastName), [
                'language' => 'en',
                'pageSize' => 20,
            ]);

        if (! $response->ok()) {
            Log::warning('ISBNdb author fallback search failed', ['status' => $response->status()]);
            return [];
        }

        $books = $response->json()['books'] ?? [];

        // Filter to books by this author that contain at least one significant
        // title word (skip stopwords so "The Bible Tells Me So" → ["bible","tells"]).
        $stopwords  = ['a', 'an', 'the', 'of', 'in', 'on', 'at', 'to', 'for',
                       'and', 'or', 'but', 'is', 'it', 'my', 'me', 'so', 'no', 'as'];
        $titleWords = array_filter(
            preg_split('/\s+/', strtolower($searchTitle)),
            fn ($w) => strlen($w) > 2 && ! in_array($w, $stopwords)
        );

        return array_values(array_filter($books, function ($book) use ($lastName, $titleWords) {
            // Must be by this author
            $bookAuthors = strtolower(implode(' ', $book['authors'] ?? []));
            if (! str_contains($bookAuthors, $lastName)) {
                return false;
            }
            // Must share at least one significant title word
            $bookTitle = strtolower($book['title'] ?? '');
            foreach ($titleWords as $word) {
                if (str_contains($bookTitle, $word)) {
                    return true;
                }
            }
            return false;
        }));
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

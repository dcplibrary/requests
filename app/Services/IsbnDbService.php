<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IsbnDbService
{
    private string $apiKey;
    private string $baseUrl = 'https://api2.isbndb.com';

    public function __construct()
    {
        $this->apiKey = config('services.isbndb.key', '');
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
            // Search by title first; author narrows down in result filtering
            $response = Http::timeout(10)
                ->withHeaders(['Authorization' => $this->apiKey])
                ->get("{$this->baseUrl}/books/{$title}", [
                    'page'     => 1,
                    'pageSize' => 10,
                    'column'   => 'title',
                ]);

            if (! $response->ok()) {
                Log::warning('ISBNdb search failed', ['status' => $response->status()]);
                return ['results' => [], 'total' => 0];
            }

            $data = $response->json();
            $books = $data['books'] ?? [];
            $total = $data['total'] ?? count($books);

            // Filter by author similarity
            if ($author) {
                $normalizedAuthor = strtolower(trim($author));
                $books = array_filter($books, function ($book) use ($normalizedAuthor) {
                    $bookAuthors = implode(' ', $book['authors'] ?? []);
                    return str_contains(strtolower($bookAuthors), explode(' ', $normalizedAuthor)[0]);
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

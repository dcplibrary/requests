<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for IsbnDbService internals.
 *
 * These tests exercise the response-parsing and author-filter logic without
 * making any real HTTP calls.
 */
class IsbnDbServiceTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Inline stub — copies the private methods under test so we don't need
    // a Laravel container or HTTP client.
    // ---------------------------------------------------------------------------

    private function service(): object
    {
        return new class {
            public function normalizeBook(array $book): array
            {
                return [
                    'isbn'          => $book['isbn'] ?? null,
                    'isbn13'        => $book['isbn13'] ?? null,
                    'title'         => $book['title'] ?? '',
                    'title_long'    => $book['title_long'] ?? '',
                    'authors'       => $book['authors'] ?? [],
                    'author_string' => implode(', ', $book['authors'] ?? []),
                    'publisher'     => $book['publisher'] ?? null,
                    'publish_date'  => $book['date_published'] ?? null,
                    'edition'       => $book['edition'] ?? null,
                    'overview'      => $book['overview'] ?? null,
                    'image'         => $book['image'] ?? null,
                    'binding'       => $book['binding'] ?? null,
                ];
            }

            public function sanitizeTitle(string $title): string
            {
                return trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-zA-Z0-9\s]/u', ' ', $title)));
            }

            public function extractLastName(string $author): string
            {
                $author = trim($author);
                if (str_contains($author, ',')) {
                    return trim(explode(',', $author)[0]);
                }
                $parts = preg_split('/\s+/', $author);
                return end($parts) ?: $author;
            }

            public function filterByAuthor(array $books, string $author): array
            {
                if (! $author) {
                    return $books;
                }
                $lastName = strtolower($this->extractLastName($author));
                return array_values(array_filter($books, function ($book) use ($lastName) {
                    $bookAuthors = strtolower(implode(' ', $book['authors'] ?? []));
                    return str_contains($bookAuthors, $lastName);
                }));
            }

            public function parseResponse(array $data, string $author): array
            {
                $books = $data['books'] ?? [];
                $total = $data['total'] ?? count($books);

                if ($author) {
                    $books = $this->filterByAuthor($books, $author);
                }

                $results = array_values(array_map(fn ($b) => $this->normalizeBook($b), $books));
                return ['results' => array_slice($results, 0, 5), 'total' => $total];
            }
        };
    }

    // ---------------------------------------------------------------------------
    // Fixture — matches the real ISBNdb response shape for "Vigil" by Saunders
    // ---------------------------------------------------------------------------

    private function vigilFixture(): array
    {
        return [
            'total' => 3,
            'books' => [
                [
                    'title'          => 'Vigil',
                    'title_long'     => 'Vigil: A Novel',
                    'isbn'           => '0593447891',
                    'isbn13'         => '9780593447895',
                    'authors'        => ['George Saunders'],
                    'publisher'      => 'Random House',
                    'date_published' => '2026',
                    'edition'        => 'First Edition',
                    'overview'       => 'A darkly comic novel...',
                    'image'          => 'https://images.isbndb.com/covers/78/95/9780593447895.jpg',
                    'binding'        => 'Hardcover',
                ],
                [
                    'title'          => 'Vigil',
                    'title_long'     => 'Vigil',
                    'isbn'           => '0593447909',
                    'isbn13'         => '9780593447900',
                    'authors'        => ['George Saunders'],
                    'publisher'      => 'Random House',
                    'date_published' => '2026',
                    'edition'        => null,
                    'overview'       => null,
                    'image'          => null,
                    'binding'        => 'Audio CD',
                ],
                [
                    // Unrelated book that also has "vigil" in the title
                    'title'          => 'Vigil of Spies',
                    'title_long'     => 'Vigil of Spies',
                    'isbn'           => '0312944128',
                    'isbn13'         => '9780312944124',
                    'authors'        => ['Owen Parry'],
                    'publisher'      => 'Forge',
                    'date_published' => '2005',
                    'edition'        => null,
                    'overview'       => null,
                    'image'          => null,
                    'binding'        => 'Hardcover',
                ],
            ],
        ];
    }

    // ---------------------------------------------------------------------------
    // normalizeBook()
    // ---------------------------------------------------------------------------

    #[Test]
    public function it_normalizes_all_expected_fields(): void
    {
        $raw = $this->vigilFixture()['books'][0];
        $normalized = $this->service()->normalizeBook($raw);

        $this->assertSame('0593447891',       $normalized['isbn']);
        $this->assertSame('9780593447895',    $normalized['isbn13']);
        $this->assertSame('Vigil',            $normalized['title']);
        $this->assertSame('Vigil: A Novel',   $normalized['title_long']);
        $this->assertSame(['George Saunders'], $normalized['authors']);
        $this->assertSame('George Saunders',  $normalized['author_string']);
        $this->assertSame('Random House',     $normalized['publisher']);
        $this->assertSame('2026',             $normalized['publish_date']);
        $this->assertSame('First Edition',    $normalized['edition']);
        $this->assertSame('Hardcover',        $normalized['binding']);
        $this->assertStringContainsString('9780593447895', $normalized['image']);
    }

    #[Test]
    public function it_normalizes_missing_fields_to_null_or_empty(): void
    {
        $normalized = $this->service()->normalizeBook([]);

        $this->assertNull($normalized['isbn']);
        $this->assertNull($normalized['isbn13']);
        $this->assertSame('', $normalized['title']);
        $this->assertSame([], $normalized['authors']);
        $this->assertSame('', $normalized['author_string']);
        $this->assertNull($normalized['publisher']);
        $this->assertNull($normalized['publish_date']);
        $this->assertNull($normalized['edition']);
        $this->assertNull($normalized['image']);
        $this->assertNull($normalized['binding']);
    }

    #[Test]
    public function it_joins_multiple_authors_into_author_string(): void
    {
        $normalized = $this->service()->normalizeBook([
            'authors' => ['Alice Author', 'Bob Coauthor'],
        ]);

        $this->assertSame('Alice Author, Bob Coauthor', $normalized['author_string']);
    }

    // ---------------------------------------------------------------------------
    // extractLastName()
    // ---------------------------------------------------------------------------

    #[Test]
    public function it_extracts_last_name_from_first_last_format(): void
    {
        $this->assertSame('Saunders', $this->service()->extractLastName('George Saunders'));
        $this->assertSame('McFadden', $this->service()->extractLastName('Freida McFadden'));
        $this->assertSame('McCarthy', $this->service()->extractLastName('Cormac McCarthy'));
    }

    #[Test]
    public function it_extracts_last_name_from_marc_format(): void
    {
        // MARC / "Last, First" format used by some catalogues and ISBNdb
        $this->assertSame('McFadden', $this->service()->extractLastName('McFadden, Freida'));
        $this->assertSame('Saunders', $this->service()->extractLastName('Saunders, George'));
    }

    #[Test]
    public function it_handles_single_word_author(): void
    {
        $this->assertSame('Prince', $this->service()->extractLastName('Prince'));
    }

    // ---------------------------------------------------------------------------
    // sanitizeTitle()
    // ---------------------------------------------------------------------------

    #[Test]
    public function it_strips_dots_from_title(): void
    {
        $this->assertSame('Worst person ever', $this->service()->sanitizeTitle('Worst.person.ever'));
    }

    #[Test]
    public function it_strips_other_punctuation_from_title(): void
    {
        $this->assertSame('Hello World', $this->service()->sanitizeTitle('Hello, World!'));
        $this->assertSame('The Hitchhiker s Guide', $this->service()->sanitizeTitle("The Hitchhiker's Guide"));
    }

    #[Test]
    public function it_collapses_multiple_spaces_in_title(): void
    {
        $this->assertSame('Word Word Word', $this->service()->sanitizeTitle('Word...Word...Word'));
    }

    #[Test]
    public function it_leaves_clean_titles_unchanged(): void
    {
        $this->assertSame('Vigil A Novel', $this->service()->sanitizeTitle('Vigil A Novel'));
    }

    // ---------------------------------------------------------------------------
    // filterByAuthor()
    // ---------------------------------------------------------------------------

    #[Test]
    public function it_filters_books_by_last_name_of_author(): void
    {
        $books  = $this->vigilFixture()['books'];
        $filtered = $this->service()->filterByAuthor($books, 'George Saunders');

        // Should keep the two Saunders books, drop Owen Parry's
        $this->assertCount(2, $filtered);
        foreach ($filtered as $b) {
            $this->assertContains('George Saunders', $b['authors']);
        }
    }

    #[Test]
    public function it_is_case_insensitive_in_author_filter(): void
    {
        $books    = $this->vigilFixture()['books'];
        $filtered = $this->service()->filterByAuthor($books, 'george saunders');

        $this->assertCount(2, $filtered);
    }

    #[Test]
    public function it_returns_all_books_when_author_is_empty(): void
    {
        $books    = $this->vigilFixture()['books'];
        $filtered = $this->service()->filterByAuthor($books, '');

        $this->assertCount(3, $filtered);
    }

    // ---------------------------------------------------------------------------
    // parseResponse() — full pipeline
    // ---------------------------------------------------------------------------

    #[Test]
    public function it_returns_total_from_api_response(): void
    {
        $result = $this->service()->parseResponse($this->vigilFixture(), 'George Saunders');

        // total comes from the raw API total, not the filtered count
        $this->assertSame(3, $result['total']);
    }

    #[Test]
    public function it_filters_results_by_author_and_normalizes(): void
    {
        $result = $this->service()->parseResponse($this->vigilFixture(), 'George Saunders');

        $this->assertCount(2, $result['results']);
        $this->assertSame('Vigil',      $result['results'][0]['title']);
        $this->assertSame('Hardcover',  $result['results'][0]['binding']);
        $this->assertSame('Audio CD',   $result['results'][1]['binding']);
    }

    #[Test]
    public function it_caps_results_at_five(): void
    {
        $fixture = ['total' => 10, 'books' => []];
        for ($i = 1; $i <= 8; $i++) {
            $fixture['books'][] = [
                'title'   => "Book {$i}",
                'authors' => ['George Saunders'],
                'isbn13'  => "97800000000{$i}",
            ];
        }

        $result = $this->service()->parseResponse($fixture, 'George Saunders');

        $this->assertCount(5, $result['results']);
    }

    #[Test]
    public function it_returns_empty_results_for_empty_response(): void
    {
        $result = $this->service()->parseResponse(['books' => [], 'total' => 0], 'George Saunders');

        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['results']);
    }

    #[Test]
    public function it_handles_missing_total_by_counting_books(): void
    {
        $fixture = [
            'books' => [
                ['title' => 'Vigil', 'authors' => ['George Saunders']],
                ['title' => 'Vigil', 'authors' => ['George Saunders']],
            ],
            // no 'total' key
        ];

        $result = $this->service()->parseResponse($fixture, 'George Saunders');

        $this->assertSame(2, $result['total']);
    }
}

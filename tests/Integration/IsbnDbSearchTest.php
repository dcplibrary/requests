<?php

namespace Dcplibrary\Requests\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live integration tests against the ISBNdb v2 API.
 *
 * Requires ISBNDB_API_KEY to be set in the environment. Tests are skipped
 * automatically when the key is absent so CI passes without credentials.
 *
 * Run selectively:
 *   ISBNDB_API_KEY=yourkey vendor/bin/phpunit --group integration
 *
 * These tests use the same real-world titles as the Bibliocommons integration
 * tests so we can confirm both catalog systems agree a book exists.
 */
#[Group('integration')]
class IsbnDbSearchTest extends TestCase
{
    private string $apiKey;
    private string $baseUrl = 'https://api2.isbndb.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->apiKey = getenv('ISBNDB_API_KEY') ?: '';

        if (empty($this->apiKey)) {
            $this->markTestSkipped('ISBNDB_API_KEY not set — skipping live ISBNdb tests.');
        }
    }

    private function search(string $title, string $author): array
    {
        $url = "{$this->baseUrl}/books/" . urlencode($title) . '?page=1&pageSize=10&column=title';

        $ctx = stream_context_create(['http' => [
            'timeout' => 15,
            'header'  => implode("\r\n", [
                "User-Agent: RequestsBot/1.0",
                "Accept: application/json",
                "Authorization: {$this->apiKey}",
            ]),
        ]]);

        $body = @file_get_contents($url, false, $ctx);
        $this->assertNotFalse($body, "HTTP request to ISBNdb failed for: {$url}");

        $data = json_decode($body, true);
        $this->assertIsArray($data, 'ISBNdb did not return valid JSON');
        $this->assertArrayNotHasKey('message', $data, 'ISBNdb returned an error: ' . ($data['message'] ?? ''));

        // Filter by first word of author (matching the service's logic)
        $books = $data['books'] ?? [];
        if ($author) {
            $firstWord = strtolower(explode(' ', trim($author))[0]);
            $books = array_values(array_filter($books, function ($book) use ($firstWord) {
                return str_contains(strtolower(implode(' ', $book['authors'] ?? [])), $firstWord);
            }));
        }

        return [
            'total'   => $data['total'] ?? count($data['books'] ?? []),
            'results' => array_slice($books, 0, 5),
            'raw'     => $data,
        ];
    }

    // ---------------------------------------------------------------------------
    // "Vigil" by George Saunders (2026)
    //
    // Same title used for the failing request #1 in the catalog test.
    // Confirms ISBNdb also knows about this book.
    // ---------------------------------------------------------------------------

    #[Test]
    public function vigil_by_george_saunders_is_found_in_isbndb(): void
    {
        $data = $this->search('vigil', 'George Saunders');

        $this->assertGreaterThan(0, count($data['results']),
            'Expected at least 1 result for "Vigil" by George Saunders');

        $first = $data['results'][0];
        $this->assertStringContainsStringIgnoringCase('vigil', $first['title']);
        $this->assertContains('George Saunders', $first['authors']);
        $this->assertNotEmpty($first['isbn13'], 'Expected a non-empty isbn13');
    }

    // ---------------------------------------------------------------------------
    // "My Husband's Wife" by Alice Feeney (2026)
    //
    // Same title used in the Bibliocommons integration test.
    // ---------------------------------------------------------------------------

    #[Test]
    public function my_husbands_wife_by_alice_feeney_is_found_in_isbndb(): void
    {
        $data = $this->search("My Husband's Wife", 'Alice Feeney');

        $this->assertGreaterThan(0, count($data['results']),
            'Expected at least 1 result for "My Husband\'s Wife" by Alice Feeney');

        $first = $data['results'][0];
        $this->assertStringContainsStringIgnoringCase("husband", $first['title']);
        $this->assertNotEmpty($first['isbn13']);
    }

    // ---------------------------------------------------------------------------
    // "Dear Debbie" by Frieda McFadden (2026)
    //
    // Same title used in the Bibliocommons integration test for the
    // author-spelling edge case.
    // ---------------------------------------------------------------------------

    #[Test]
    public function dear_debbie_by_frieda_mcfadden_is_found_in_isbndb(): void
    {
        $data = $this->search('Dear Debbie', 'Frieda McFadden');

        $this->assertGreaterThan(0, count($data['results']),
            'Expected at least 1 result for "Dear Debbie" by Frieda McFadden');

        $first = $data['results'][0];
        $this->assertStringContainsStringIgnoringCase('dear debbie', $first['title']);
        $this->assertNotEmpty($first['isbn13']);
    }

    // ---------------------------------------------------------------------------
    // Sanity: a fabricated title should return 0 author-filtered results
    // ---------------------------------------------------------------------------

    #[Test]
    public function nonexistent_title_returns_zero_results(): void
    {
        $data = $this->search('Zzzz Nonexistent Title Xyz', 'Nobody Authorname');

        $this->assertCount(0, $data['results'],
            'Expected 0 results for a fabricated title/author');
    }

    // ---------------------------------------------------------------------------
    // API contract: response always has expected shape
    // ---------------------------------------------------------------------------

    #[Test]
    public function api_response_contains_expected_fields(): void
    {
        $data = $this->search('vigil', 'George Saunders');

        $this->assertArrayHasKey('raw', $data);
        $this->assertArrayHasKey('books', $data['raw']);
        $this->assertArrayHasKey('total', $data['raw']);

        if (! empty($data['results'])) {
            $book = $data['results'][0];
            foreach (['title', 'authors', 'isbn13'] as $field) {
                $this->assertArrayHasKey($field, $book,
                    "Expected field '{$field}' in ISBNdb book record");
            }
        }
    }
}

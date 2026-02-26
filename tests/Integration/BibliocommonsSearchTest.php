<?php

namespace Dcplibrary\Sfp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;

/**
 * Live integration tests against the Bibliocommons gateway API.
 *
 * These tests make real HTTP requests. Run them selectively:
 *   vendor/bin/phpunit --group integration
 *
 * They verify that the query-building logic actually returns results for
 * books we know exist in the DCPL catalog.
 */
#[Group('integration')]
class BibliocommonsSearchTest extends TestCase
{
    private string $slug = 'dcpl';

    private function search(string $title, string $author, string $audience = 'adult', ?string $year = null): array
    {
        $lastName = $this->extractLastName($author);

        $parts = ['title:(' . $title . ')', 'contributor:(' . $lastName . ')'];
        if ($audience) {
            $parts[] = 'audience:"' . $audience . '"';
        }
        if ($year && (int) $year >= (date('Y') - 2)) {
            $parts[] = 'pubyear:[' . max(1, (int) $year - 1) . ' TO ' . ((int) $year + 1) . ']';
        }
        $query = implode(' ', $parts);

        $url = "https://gateway.bibliocommons.com/v2/libraries/{$this->slug}/bibs/search?"
            . http_build_query(['query' => $query, 'searchType' => 'bl', 'suppress' => 'true']);

        $ctx  = stream_context_create(['http' => [
            'timeout' => 15,
            'header'  => "User-Agent: Mozilla/5.0 (compatible; SfpBot/1.0)\r\nAccept: application/json\r\n",
        ]]);

        $body = @file_get_contents($url, false, $ctx);
        $this->assertNotFalse($body, "HTTP request to Bibliocommons gateway failed for: {$url}");

        $data = json_decode($body, true);
        $this->assertIsArray($data, 'Bibliocommons gateway did not return valid JSON');

        return $data;
    }

    private function extractLastName(string $author): string
    {
        $author = trim($author);
        if (str_contains($author, ',')) {
            return trim(explode(',', $author)[0]);
        }
        $parts = preg_split('/\s+/', $author);
        return end($parts) ?: $author;
    }

    // ---------------------------------------------------------------------------
    // "Dear Debbie" by Frieda McFadden (bib S123C908281)
    //
    // This was the failing case that prompted the fix. The catalog stores the
    // author as "McFadden, Freida" (note the misspelling). Searching by full
    // first name "Frieda McFadden" returned 0 results. Using last name only
    // ("McFadden") finds the record.
    // ---------------------------------------------------------------------------

    #[Test]
    public function dear_debbie_by_frieda_mcfadden_is_found_in_catalog(): void
    {
        $data  = $this->search('Dear Debbie', 'Frieda McFadden', 'adult', '2026');
        $total = $data['catalogSearch']['pagination']['count'] ?? 0;
        $bibs  = $data['entities']['bibs'] ?? [];

        $this->assertGreaterThan(0, $total, 'Expected at least 1 result for "Dear Debbie" by Frieda McFadden');

        // The known physical book bib must be in the result set
        $this->assertArrayHasKey(
            'S123C908281',
            $bibs,
            'Expected bib S123C908281 (Dear Debbie, BK) in results'
        );

        $bib = $bibs['S123C908281'];
        $this->assertSame('Dear Debbie',      $bib['briefInfo']['title']);
        $this->assertSame('BK',               $bib['briefInfo']['format']);
        $this->assertContains('McFadden, Freida', $bib['briefInfo']['authors']);
    }

    // ---------------------------------------------------------------------------
    // "My Husband's Wife" by Alice Feeney (bib S123C906661)
    //
    // The first title tested. Verified the gateway API returns results; also
    // confirms that first-name search ("Alice Feeney") works correctly here
    // because the catalog spelling matches exactly.
    // ---------------------------------------------------------------------------

    #[Test]
    public function my_husbands_wife_by_alice_feeney_is_found_in_catalog(): void
    {
        $data  = $this->search("My Husband's Wife", 'Alice Feeney', 'adult', '2026');
        $total = $data['catalogSearch']['pagination']['count'] ?? 0;
        $bibs  = $data['entities']['bibs'] ?? [];

        $this->assertGreaterThan(0, $total, 'Expected at least 1 result for "My Husband\'s Wife" by Alice Feeney');

        $this->assertArrayHasKey(
            'S123C906661',
            $bibs,
            'Expected bib S123C906661 (My Husband\'s Wife, BK) in results'
        );

        $bib = $bibs['S123C906661'];
        $this->assertSame("My Husband's Wife", $bib['briefInfo']['title']);
        $this->assertSame('BK',                $bib['briefInfo']['format']);
    }

    // ---------------------------------------------------------------------------
    // "Blood Meridian" by Cormac McCarthy (originally 1985)
    //
    // Classic title where the patron enters the original pub year but the
    // catalog holds later digital editions. The year filter must be suppressed
    // for old titles or the search returns 0 even though the book is held.
    // ---------------------------------------------------------------------------

    #[Test]
    public function blood_meridian_by_cormac_mccarthy_is_found_without_year_filter(): void
    {
        $data  = $this->search('Blood Meridian', 'Cormac McCarthy', 'adult', '1985');
        $total = $data['catalogSearch']['pagination']['count'] ?? 0;

        $this->assertGreaterThan(0, $total,
            'Expected results for "Blood Meridian" — year filter must not restrict old pub years');
    }

    // ---------------------------------------------------------------------------
    // Sanity: a title that genuinely does not exist should return 0 results
    // ---------------------------------------------------------------------------

    #[Test]
    public function nonexistent_title_returns_zero_results(): void
    {
        $data  = $this->search('Zzzz Nonexistent Title Xyz', 'Nobody Authorname', 'adult', null);
        $total = $data['catalogSearch']['pagination']['count'] ?? 0;

        $this->assertSame(0, $total, 'Expected 0 results for a fabricated title');
    }
}

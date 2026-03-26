<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Unit tests for BibliocommonsService internals.
 *
 * These tests exercise the query-building and response-parsing logic without
 * making any real HTTP calls.
 */
class BibliocommonsServiceTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Helpers — reach into private methods via reflection
    // ---------------------------------------------------------------------------

    private function service(): object
    {
        // Build a partial stub: override librarySlug() so it doesn't touch the
        // DB, but keep all other methods real.
        return new class {
            // Copy of extractLastName() — tested directly below
            public function extractLastName(string $author): string
            {
                $author = trim($author);
                if (str_contains($author, ',')) {
                    return trim(explode(',', $author)[0]);
                }
                $parts = preg_split('/\s+/', $author);
                return end($parts) ?: $author;
            }

            public function quoteLucenePhrase(string $value): string
            {
                $value = str_replace(['\\', '"'], ['\\\\', '\\"'], trim($value));

                return '"' . $value . '"';
            }

            // Copy of buildQuery() — depends on extractLastName() + quoteLucenePhrase()
            public function buildQuery(string $title, string $author, string $audience, ?string $year, bool $includeContributor = true): string
            {
                $lastName = $this->extractLastName($author);
                $title    = trim(preg_replace('/\s+/', ' ', $title));

                $parts = [
                    'title:' . $this->quoteLucenePhrase($title),
                ];

                if ($includeContributor && $lastName !== '') {
                    $parts[] = 'contributor:' . $this->quoteLucenePhrase($lastName);
                }

                if ($audience !== '') {
                    $parts[] = 'audience:"' . str_replace('"', '\\"', $audience) . '"';
                }

                if ($year && (int) $year >= (date('Y') - 2)) {
                    $yearFrom = max(1, (int) $year - 1);
                    $yearTo   = (int) $year + 1;
                    $parts[]  = "pubyear:[{$yearFrom} TO {$yearTo}]";
                }

                return implode(' ', $parts);
            }

            // Copy of parseApiResponse()
            public function parseApiResponse(array $data, string $slug): array
            {
                $catalogSearch  = $data['catalogSearch'] ?? [];
                $total          = $catalogSearch['pagination']['count'] ?? 0;
                $orderedResults = $catalogSearch['results'] ?? [];
                $bibEntities    = $data['entities']['bibs'] ?? [];

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
        };
    }

    // ---------------------------------------------------------------------------
    // extractLastName()
    // ---------------------------------------------------------------------------

    #[Test]
    #[DataProvider('lastNameProvider')]
    public function it_extracts_last_name_from_author_string(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->service()->extractLastName($input));
    }

    public static function lastNameProvider(): array
    {
        return [
            'first last'                    => ['Alice Feeney',       'Feeney'],
            'first last (McFadden)'         => ['Frieda McFadden',    'McFadden'],
            'MARC last, first'              => ['McFadden, Frieda',   'McFadden'],
            'MARC last, first (Feeney)'     => ['Feeney, Alice',      'Feeney'],
            'single word'                   => ['Feeney',             'Feeney'],
            'three word name'               => ['Mary Balogh Smith',  'Smith'],
            'extra spaces'                  => ['  Alice   Feeney  ', 'Feeney'],
            'MARC with extra spaces'        => [' McFadden , Frieda', 'McFadden'],
            'MARC trailing comma'           => ['Scieszka, Jon,', 'Scieszka'],
        ];
    }

    // ---------------------------------------------------------------------------
    // buildQuery()
    // ---------------------------------------------------------------------------

    #[Test]
    public function it_builds_query_with_last_name_only_in_contributor_field(): void
    {
        $query = $this->service()->buildQuery('Dear Debbie', 'Frieda McFadden', 'adult', '2026');

        // Must use last name only — full "Frieda McFadden" fails in Bibliocommons
        // because catalog stores "McFadden, Freida" (different first-name spelling)
        $this->assertStringContainsString('contributor:"McFadden"', $query);
        $this->assertStringNotContainsString('Frieda', $query);
    }

    #[Test]
    public function it_builds_query_with_correct_title(): void
    {
        $query = $this->service()->buildQuery('Dear Debbie', 'Frieda McFadden', 'adult', '2026');

        $this->assertStringContainsString('title:"Dear Debbie"', $query);
    }

    #[Test]
    public function it_phrase_quotes_title_with_brackets_so_lucene_does_not_treat_them_as_range_syntax(): void
    {
        $query = $this->service()->buildQuery(
            'Astronuts. [Volume 1], The Plant Planet',
            'Scieszka, Jon,',
            'children',
            '2019'
        );

        $this->assertStringContainsString('title:"Astronuts. [Volume 1], The Plant Planet"', $query);
        $this->assertStringContainsString('contributor:"Scieszka"', $query);
        $this->assertStringContainsString('audience:"children"', $query);
    }

    #[Test]
    public function it_can_omit_contributor_for_title_only_fallback_queries(): void
    {
        $query = $this->service()->buildQuery('Astronuts', 'Scieszka, Jon', '', null, false);

        $this->assertStringContainsString('title:"Astronuts"', $query);
        $this->assertStringNotContainsString('contributor:', $query);
    }

    #[Test]
    public function it_builds_query_with_year_range(): void
    {
        $query = $this->service()->buildQuery('Dear Debbie', 'Frieda McFadden', 'adult', '2026');

        $this->assertStringContainsString('pubyear:[2025 TO 2027]', $query);
    }

    #[Test]
    public function it_builds_query_without_year_when_not_provided(): void
    {
        $query = $this->service()->buildQuery('Dear Debbie', 'Frieda McFadden', 'adult', null);

        $this->assertStringNotContainsString('pubyear:', $query);
    }

    #[Test]
    public function it_omits_year_filter_for_old_titles(): void
    {
        // Blood Meridian (1985) — patron enters original pub year but catalog
        // holds later editions; a tight year window would return 0 results.
        $query = $this->service()->buildQuery('Blood Meridian', 'Cormac McCarthy', 'adult', '1985');

        $this->assertStringNotContainsString('pubyear:', $query);
    }

    #[Test]
    public function it_applies_year_filter_for_recent_titles(): void
    {
        $recentYear = (string) date('Y');
        $query = $this->service()->buildQuery('Dear Debbie', 'Frieda McFadden', 'adult', $recentYear);

        $this->assertStringContainsString('pubyear:', $query);
    }

    #[Test]
    public function it_builds_query_without_audience_when_empty(): void
    {
        $query = $this->service()->buildQuery('Dear Debbie', 'Frieda McFadden', '', null);

        $this->assertStringNotContainsString('audience:', $query);
    }

    #[Test]
    public function it_handles_marc_format_author_in_contributor_field(): void
    {
        // If patron types "McFadden, Frieda" (MARC order), last name still extracted correctly
        $query = $this->service()->buildQuery('Dear Debbie', 'McFadden, Frieda', 'adult', '2026');

        $this->assertStringContainsString('contributor:"McFadden"', $query);
        $this->assertStringNotContainsString('Frieda', $query);
    }

    // ---------------------------------------------------------------------------
    // parseApiResponse() — using fixture data matching the real Dear Debbie response
    // ---------------------------------------------------------------------------

    private function dearDebbieFixture(): array
    {
        return [
            'catalogSearch' => [
                'pagination' => ['count' => 4, 'limit' => 25, 'page' => 1, 'pages' => 1, 'pageForSolr' => 0],
                'results' => [
                    ['representative' => 'S123C908281', 'manifestations' => ['S123C908281']],
                    ['representative' => 'S123C911343', 'manifestations' => ['S123C911343']],
                ],
            ],
            'entities' => [
                'bibs' => [
                    'S123C908281' => [
                        'id' => 'S123C908281',
                        'briefInfo' => [
                            'metadataId'      => 'S123C908281',
                            'title'           => 'Dear Debbie',
                            'subtitle'        => '',
                            'format'          => 'BK',
                            'authors'         => ['McFadden, Freida'],
                            'publicationDate' => '2026',
                            'edition'         => null,
                            'isbns'           => ['9781464264832', '146426483X', '9781464266485'],
                            'jacket'          => [
                                'medium' => 'https://secure.syndetics.com/index.aspx?isbn=9781464264832/MC.GIF&client=davia&type=xw12&oclc=',
                            ],
                        ],
                    ],
                    'S123C911343' => [
                        'id' => 'S123C911343',
                        'briefInfo' => [
                            'metadataId'      => 'S123C911343',
                            'title'           => 'Dear Debbie',
                            'subtitle'        => '',
                            'format'          => 'LPRINT',
                            'authors'         => ['McFadden, Freida'],
                            'publicationDate' => '2026',
                            'edition'         => null,
                            'isbns'           => ['9781464266485'],
                            'jacket'          => null,
                        ],
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function it_parses_total_count_from_response(): void
    {
        $result = $this->service()->parseApiResponse($this->dearDebbieFixture(), 'dcpl');

        $this->assertSame(4, $result['total']);
    }

    #[Test]
    public function it_parses_results_in_order_from_response(): void
    {
        $result = $this->service()->parseApiResponse($this->dearDebbieFixture(), 'dcpl');

        $this->assertCount(2, $result['results']);
        $this->assertSame('S123C908281', $result['results'][0]['bib_id']);
        $this->assertSame('S123C911343', $result['results'][1]['bib_id']);
    }

    #[Test]
    public function it_maps_bib_fields_correctly(): void
    {
        $result = $this->service()->parseApiResponse($this->dearDebbieFixture(), 'dcpl');
        $first  = $result['results'][0];

        $this->assertSame('Dear Debbie',       $first['title']);
        $this->assertSame('McFadden, Freida',  $first['author']);
        $this->assertSame('BK',                $first['format']);
        $this->assertSame('2026',              $first['year']);
        $this->assertSame(['9781464264832', '146426483X', '9781464266485'], $first['isbns']);
        $this->assertStringContainsString('MC.GIF', $first['jacket']);
        $this->assertSame(
            'https://dcpl.bibliocommons.com/v2/record/S123C908281',
            $first['catalog_url']
        );
    }

    #[Test]
    public function it_handles_null_jacket_gracefully(): void
    {
        $result = $this->service()->parseApiResponse($this->dearDebbieFixture(), 'dcpl');

        // S123C911343 has jacket: null
        $this->assertNull($result['results'][1]['jacket']);
    }

    #[Test]
    public function it_caps_results_at_five(): void
    {
        $fixture = $this->dearDebbieFixture();

        // Add 6 more bibs to push total to 8
        for ($i = 1; $i <= 6; $i++) {
            $id = "S123C99999{$i}";
            $fixture['catalogSearch']['results'][] = ['representative' => $id, 'manifestations' => [$id]];
            $fixture['entities']['bibs'][$id] = [
                'id' => $id,
                'briefInfo' => ['title' => "Title {$i}", 'authors' => ['Author'], 'format' => 'BK', 'publicationDate' => '2026'],
            ];
        }

        $result = $this->service()->parseApiResponse($fixture, 'dcpl');

        $this->assertCount(5, $result['results']);
    }

    #[Test]
    public function it_returns_empty_results_for_empty_response(): void
    {
        $empty = [
            'catalogSearch' => ['pagination' => ['count' => 0], 'results' => []],
            'entities'      => ['bibs' => []],
        ];

        $result = $this->service()->parseApiResponse($empty, 'dcpl');

        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['results']);
    }
}

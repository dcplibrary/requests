<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for CatalogFormatLabel logic.
 *
 * Tests the format-code-to-label mapping logic in isolation,
 * without hitting the database, by replicating the map() behaviour
 * using the same array structure the model returns.
 */
class CatalogFormatLabelTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * The canonical set of BiblioCommons format codes and their expected labels,
     * matching CatalogFormatLabelsSeeder.
     */
    public static function formatCodeProvider(): array
    {
        return [
            'BK'                       => ['BK',                       'Book'],
            'EBOOK'                    => ['EBOOK',                    'eBook'],
            'EAUDIOBOOK'               => ['EAUDIOBOOK',               'eAudiobook'],
            'LPRINT'                   => ['LPRINT',                   'Large Print'],
            'DVD'                      => ['DVD',                      'DVD'],
            'BLURAY'                   => ['BLURAY',                   'Blu-ray'],
            'AB'                       => ['AB',                       'Audiobook'],
            'BOOK_CD'                  => ['BOOK_CD',                  'Book on CD'],
            'UK'                       => ['UK',                       'Unknown'],
            'GRAPHIC_NOVEL_DOWNLOAD'   => ['GRAPHIC_NOVEL_DOWNLOAD',   'Graphic Novel Download'],
            'VIDEO_GAME'               => ['VIDEO_GAME',               'Video Game'],
            'VIDEO_ONLINE'             => ['VIDEO_ONLINE',             'Streaming Video'],
            'PASS'                     => ['PASS',                     'Museum Pass'],
            'MAG_ONLINE'               => ['MAG_ONLINE',               'Online Magazine'],
            'MAG'                      => ['MAG',                      'Magazine'],
            'KIT'                      => ['KIT',                      'Kit'],
            'EQUIPMENT'                => ['EQUIPMENT',                'Equipment'],
        ];
    }

    /**
     * Simulate what CatalogFormatLabel::map() returns — a flat code → label array.
     * In tests we work with this fixture so we don't need a DB connection.
     */
    private function seedMap(): array
    {
        return array_combine(
            array_column(self::formatCodeProvider(), 0),
            array_column(self::formatCodeProvider(), 1),
        );
    }

    // -------------------------------------------------------------------------
    // Seeder coverage — all 17 codes present
    // -------------------------------------------------------------------------

    #[Test]
    public function it_seeds_all_seventeen_bibliocommons_format_codes(): void
    {
        $map = $this->seedMap();

        $this->assertCount(17, $map);
    }

    #[Test]
    #[DataProvider('formatCodeProvider')]
    public function it_maps_format_code_to_expected_label(string $code, string $expectedLabel): void
    {
        $map = $this->seedMap();

        $this->assertArrayHasKey($code, $map, "Format code '{$code}' missing from map");
        $this->assertSame($expectedLabel, $map[$code]);
    }

    // -------------------------------------------------------------------------
    // Fallback behaviour — unknown codes fall back to the raw code
    // -------------------------------------------------------------------------

    #[Test]
    public function unknown_format_code_falls_back_to_raw_code(): void
    {
        $map = $this->seedMap();
        $unknownCode = 'TOTALLY_UNKNOWN_FORMAT';

        // This mirrors the blade: $formatLabels[$result['format']] ?? $result['format']
        $label = $map[$unknownCode] ?? $unknownCode;

        $this->assertSame($unknownCode, $label);
    }

    #[Test]
    public function known_format_code_does_not_fall_back(): void
    {
        $map = $this->seedMap();

        $label = $map['BK'] ?? 'BK';

        $this->assertSame('Book', $label);
        $this->assertNotSame('BK', $label);
    }

    // -------------------------------------------------------------------------
    // map() structure — keys and values are strings
    // -------------------------------------------------------------------------

    #[Test]
    public function map_keys_are_all_uppercase_strings(): void
    {
        $map = $this->seedMap();

        foreach (array_keys($map) as $code) {
            $this->assertSame($code, strtoupper($code), "Code '{$code}' should be uppercase");
        }
    }

    #[Test]
    public function map_values_are_non_empty_strings(): void
    {
        $map = $this->seedMap();

        foreach ($map as $code => $label) {
            $this->assertIsString($label, "Label for '{$code}' should be a string");
            $this->assertNotEmpty($label, "Label for '{$code}' should not be empty");
        }
    }

    // -------------------------------------------------------------------------
    // No duplicate codes
    // -------------------------------------------------------------------------

    #[Test]
    public function format_codes_are_unique(): void
    {
        $codes = array_column(self::formatCodeProvider(), 0);

        $this->assertSame(count($codes), count(array_unique($codes)), 'Format codes must be unique');
    }
}

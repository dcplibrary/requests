<?php

namespace Dcplibrary\Requests\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Unit tests for material payload normalization and the Material subjects cast.
 *
 * Exercises the normalizeMaterialPayload() safety net (arrays → JSON, Stringable → string)
 * and verifies the Eloquent 'array' cast on Material::$subjects round-trips correctly.
 */
class MaterialPayloadTest extends TestCase
{
    // ---------------------------------------------------------------------------
    // Inline stub — mirrors the private normalizeMaterialPayload() from the
    // CreatesEnrichedMaterial trait so we can test without a Laravel container.
    // ---------------------------------------------------------------------------

    /**
     * Normalize a material payload so each value is safe for PDO binding.
     *
     * Mirrors the private normalizeMaterialPayload() from CreatesEnrichedMaterial.
     * Fields in $castHandled are skipped because Eloquent's 'array' cast handles
     * them — encoding here would cause double-encoding.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeMaterialPayload(array $payload): array
    {
        static $castHandled = ['subjects'];

        foreach ($payload as $key => $value) {
            if (is_array($value) && ! in_array($key, $castHandled, true)) {
                $payload[$key] = json_encode(
                    $value,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                );
                continue;
            }

            if ($value instanceof \Stringable) {
                $payload[$key] = (string) $value;
            }
        }

        return $payload;
    }

    // ---------------------------------------------------------------------------
    // normalizeMaterialPayload — array handling
    // ---------------------------------------------------------------------------

    #[Test]
    public function it_json_encodes_non_cast_array_values(): void
    {
        $result = $this->normalizeMaterialPayload([
            'some_other_field' => ['Fiction', 'Science'],
        ]);

        $this->assertSame('["Fiction","Science"]', $result['some_other_field']);
    }

    #[Test]
    public function it_skips_encoding_for_cast_handled_fields(): void
    {
        $result = $this->normalizeMaterialPayload([
            'subjects' => ['Fiction', 'Science'],
        ]);

        // subjects has an Eloquent 'array' cast — must stay as a PHP array
        $this->assertIsArray($result['subjects']);
        $this->assertSame(['Fiction', 'Science'], $result['subjects']);
    }

    #[Test]
    public function it_json_encodes_nested_arrays_for_non_cast_fields(): void
    {
        $result = $this->normalizeMaterialPayload([
            'meta' => ['key' => 'value', 'nested' => [1, 2, 3]],
        ]);

        $decoded = json_decode($result['meta'], true);
        $this->assertSame(['key' => 'value', 'nested' => [1, 2, 3]], $decoded);
    }

    #[Test]
    public function it_json_encodes_empty_array_for_non_cast_fields(): void
    {
        $result = $this->normalizeMaterialPayload([
            'other_array' => [],
        ]);

        $this->assertSame('[]', $result['other_array']);
    }

    #[Test]
    public function it_preserves_unicode_in_json_encoded_non_cast_arrays(): void
    {
        $result = $this->normalizeMaterialPayload([
            'tags' => ['Ficción', 'Littérature française'],
        ]);

        $this->assertStringContainsString('Ficción', $result['tags']);
        $this->assertStringContainsString('Littérature française', $result['tags']);
    }

    // ---------------------------------------------------------------------------
    // normalizeMaterialPayload — Stringable handling
    // ---------------------------------------------------------------------------

    #[Test]
    public function it_casts_stringable_objects_to_string(): void
    {
        $stringable = new class implements \Stringable {
            public function __toString(): string
            {
                return 'stringable value';
            }
        };

        $result = $this->normalizeMaterialPayload([
            'title' => $stringable,
        ]);

        $this->assertSame('stringable value', $result['title']);
    }

    // ---------------------------------------------------------------------------
    // normalizeMaterialPayload — scalar pass-through
    // ---------------------------------------------------------------------------

    #[Test]
    public function it_passes_through_string_values_unchanged(): void
    {
        $result = $this->normalizeMaterialPayload([
            'title'     => 'Vigil',
            'author'    => 'George Saunders',
            'publisher' => 'Random House',
        ]);

        $this->assertSame('Vigil', $result['title']);
        $this->assertSame('George Saunders', $result['author']);
        $this->assertSame('Random House', $result['publisher']);
    }

    #[Test]
    public function it_passes_through_null_values_unchanged(): void
    {
        $result = $this->normalizeMaterialPayload([
            'synopsis' => null,
            'edition'  => null,
        ]);

        $this->assertNull($result['synopsis']);
        $this->assertNull($result['edition']);
    }

    #[Test]
    public function it_passes_through_integer_values_unchanged(): void
    {
        $result = $this->normalizeMaterialPayload([
            'pages' => 320,
        ]);

        $this->assertSame(320, $result['pages']);
    }

    #[Test]
    public function it_passes_through_float_values_unchanged(): void
    {
        $result = $this->normalizeMaterialPayload([
            'msrp' => 29.99,
        ]);

        $this->assertSame(29.99, $result['msrp']);
    }

    // ---------------------------------------------------------------------------
    // normalizeMaterialPayload — mixed payload (realistic ISBNdb scenario)
    // ---------------------------------------------------------------------------

    #[Test]
    public function it_normalizes_a_realistic_isbndb_payload(): void
    {
        $payload = [
            'title'      => 'Vigil',
            'author'     => 'George Saunders',
            'isbn13'     => '9780593447895',
            'publisher'  => 'Random House',
            'synopsis'   => '<p>A darkly comic novel...</p>',
            'subjects'   => ['Fiction', 'Humor', 'Literary Fiction'],
            'pages'      => 320,
            'msrp'       => 28.99,
            'binding'    => 'Hardcover',
            'language'   => 'en',
            'overview'   => null,
            'source'     => 'isbndb',
        ];

        $result = $this->normalizeMaterialPayload($payload);

        // subjects is cast-handled — stays as a PHP array for the Eloquent cast
        $this->assertIsArray($result['subjects']);
        $this->assertSame(['Fiction', 'Humor', 'Literary Fiction'], $result['subjects']);

        // Scalars pass through
        $this->assertSame('Vigil', $result['title']);
        $this->assertSame('George Saunders', $result['author']);
        $this->assertSame(320, $result['pages']);
        $this->assertNull($result['overview']);
        $this->assertSame('<p>A darkly comic novel...</p>', $result['synopsis']);
    }

    // ---------------------------------------------------------------------------
    // Material subjects cast — Eloquent 'array' cast round-trip
    // ---------------------------------------------------------------------------

    #[Test]
    public function material_subjects_cast_decodes_json_to_array(): void
    {
        // Simulate what Eloquent does: setAttribute stores JSON, getAttribute decodes.
        $material = new \Dcplibrary\Requests\Models\Material();
        $material->subjects = ['Fiction', 'Humor', 'Literary Fiction'];

        // The cast should make subjects available as an array
        $this->assertIsArray($material->subjects);
        $this->assertSame(['Fiction', 'Humor', 'Literary Fiction'], $material->subjects);
    }

    #[Test]
    public function material_subjects_cast_handles_null(): void
    {
        $material = new \Dcplibrary\Requests\Models\Material();
        $material->subjects = null;

        $this->assertNull($material->subjects);
    }

    #[Test]
    public function material_subjects_cast_handles_empty_array(): void
    {
        $material = new \Dcplibrary\Requests\Models\Material();
        $material->subjects = [];

        $this->assertIsArray($material->subjects);
        $this->assertEmpty($material->subjects);
    }

    #[Test]
    public function material_subjects_cast_stores_as_json_string(): void
    {
        $material = new \Dcplibrary\Requests\Models\Material();
        $material->subjects = ['Fiction', 'Science'];

        // The raw attribute should be a JSON string for database storage
        $raw = $material->getAttributes()['subjects'];
        $this->assertIsString($raw);
        $this->assertSame(['Fiction', 'Science'], json_decode($raw, true));
    }

    #[Test]
    public function material_subjects_round_trips_through_cast_correctly(): void
    {
        $material = new \Dcplibrary\Requests\Models\Material();
        $material->subjects = ['Fiction', 'Humor'];

        // Round-trip: set as array, cast encodes to JSON internally, get decodes back
        $this->assertIsArray($material->subjects);
        $this->assertSame(['Fiction', 'Humor'], $material->subjects);

        // The raw stored value is JSON for PDO
        $raw = $material->getAttributes()['subjects'];
        $this->assertSame('["Fiction","Humor"]', $raw);
    }
}

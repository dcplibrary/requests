<?php

namespace Dcplibrary\Requests\Livewire\Concerns;

use Dcplibrary\Requests\Models\Material;

/**
 * Shared material find-or-create logic with ISBNdb enrichment.
 *
 * Used by both RequestForm (SFP) and IllForm (ILL) to eliminate duplicated
 * Material creation and ISBNdb field-mapping code.
 */
trait CreatesEnrichedMaterial
{
    /**
     * Find or create a Material, optionally enriching with ISBNdb data.
     *
     * When ISBNdb data is provided the Material's title and author are replaced
     * with the ISBNdb values, all enrichment columns are stored, and an existing
     * material (matched by title + author) is updated in-place.
     *
     * @param  array       $baseData   Keys: title, author, publish_date, material_type_option_id.
     * @param  array|null  $isbndbData Normalized ISBNdb result from IsbnDbService.
     * @return Material
     */
    protected function findOrCreateMaterial(array $baseData, ?array $isbndbData = null): Material
    {
        $enrichment = $isbndbData ? self::mapIsbndbToMaterial($isbndbData) : [];

        // Try matching by ISBNdb title/author first, then fall back to patron-entered values.
        $material = null;
        if ($isbndbData) {
            $isbndbTitle  = $isbndbData['title'] ?? $baseData['title'];
            $isbndbAuthor = $isbndbData['author_string'] ?? $baseData['author'];
            $material     = Material::findMatch($isbndbTitle, $isbndbAuthor);
        }

        if (! $material) {
            $material = Material::findMatch($baseData['title'], $baseData['author']);
        }

        // Existing material found — enrich if ISBNdb data is available.
        if ($material) {
            if ($enrichment) {
                // Replace title/author with authoritative ISBNdb values.
                $enrichment['title']  = $isbndbData['title'] ?? $material->title;
                $enrichment['author'] = $isbndbData['author_string'] ?? $material->author;

                $material->update(self::normalizeMaterialPayload($enrichment));
            }

            return $material;
        }

        // No existing material — create a new one.
        $createData = [
            'title'                   => $isbndbData ? ($isbndbData['title'] ?? $baseData['title']) : $baseData['title'],
            'author'                  => $isbndbData ? ($isbndbData['author_string'] ?? $baseData['author']) : $baseData['author'],
            'publish_date'            => $baseData['publish_date'] ?? null,
            'material_type_option_id' => $baseData['material_type_option_id'] ?? null,
            'source'                  => $isbndbData ? 'isbndb' : 'submitted',
        ];

        return Material::create(self::normalizeMaterialPayload(array_merge($createData, $enrichment)));
    }

    /**
     * Map a normalized ISBNdb result array to Material column values.
     *
     * @param  array  $data  Normalized ISBNdb data from IsbnDbService::normalizeBook().
     * @return array<string, mixed>
     */
    private static function mapIsbndbToMaterial(array $data): array
    {
        $mapped = [
            'isbn'          => $data['isbn'] ?? null,
            'isbn13'        => $data['isbn13'] ?? null,
            'publisher'     => $data['publisher'] ?? null,
            'edition'       => $data['edition'] ?? null,
            'overview'      => $data['overview'] ?? null,
            'title_long'    => $data['title_long'] ?? null,
            'synopsis'      => $data['synopsis'] ?? null,
            'subjects'      => ! empty($data['subjects']) ? $data['subjects'] : null,
            'dewey_decimal' => $data['dewey_decimal'] ?? null,
            'pages'         => $data['pages'] ?? null,
            'language'      => $data['language'] ?? null,
            'msrp'          => $data['msrp'] ?? null,
            'binding'       => $data['binding'] ?? null,
            'dimensions'    => isset($data['dimensions'])
                                   ? (is_array($data['dimensions']) ? implode(', ', $data['dimensions']) : $data['dimensions'])
                                   : null,
            'source'        => 'isbndb',
        ];

        if (! empty($data['publish_date'])) {
            $parsed = strtotime($data['publish_date']);
            if ($parsed !== false) {
                $mapped['exact_publish_date'] = date('Y-m-d', $parsed);
            }
        }

        return $mapped;
    }

    /**
     * Normalize a material payload so each value is safe for PDO binding.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private static function normalizeMaterialPayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
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
}

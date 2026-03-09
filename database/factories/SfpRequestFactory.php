<?php

namespace Dcplibrary\Sfp\Database\Factories;

use Dcplibrary\Sfp\Models\Patron;
use Dcplibrary\Sfp\Models\SfpRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for request records (the `requests` table).
 *
 * Requires patron_id, request_status_id, and material_type_id to be set
 * either via state or by passing them in the create() / make() call.
 *
 * Default state: sfp kind, pending status (must be overridden or provided).
 * States: ill(), withMaterial(), atStatus($statusId), recent(), older().
 *
 * Usage:
 *   SfpRequestFactory::new()->create([
 *       'patron_id'         => $patron->id,
 *       'request_status_id' => $pendingStatusId,
 *       'material_type_id'  => $bookTypeId,
 *   ]);
 *
 *   SfpRequestFactory::new()->ill()->create([...]);
 */
class SfpRequestFactory extends Factory
{
    protected $model = SfpRequest::class;

    /** All "where heard" phrases seen in real submissions. */
    private const WHERE_HEARD = [
        'A friend recommended it',
        'Saw it reviewed in the newspaper',
        'Heard about it on a podcast',
        'My book club is reading it',
        'Saw the author on TV',
        'It was on a "best of" list',
        'Recommended by my teacher',
        'Found it on Goodreads',
        null,
        null,
        null,  // frequently left blank
    ];

    private const GENRES = ['fiction', 'nonfiction', null];

    public function definition(): array
    {
        // Pick a random book from the combined real+fake pool
        $allBooks = array_merge(MaterialFactory::REAL_BOOKS, MaterialFactory::FAKE_BOOKS);
        $book     = $this->faker->randomElement($allBooks);

        return [
            'patron_id'              => Patron::factory(),
            'material_id'            => null,
            'audience_id'            => null,  // override in seeder
            'material_type_id'       => null,  // override in seeder
            'request_status_id'      => null,  // override in seeder
            'request_kind'           => 'sfp',
            'submitted_title'        => $book['title'],
            'submitted_author'       => $book['author'],
            'submitted_publish_date' => $book['publish_date'] ?? null,
            'other_material_text'    => null,
            'genre'                  => $this->faker->randomElement(self::GENRES),
            'where_heard'            => $this->faker->randomElement(self::WHERE_HEARD),
            'ill_requested'          => false,
            'catalog_searched'       => false,
            'catalog_result_count'   => null,
            'catalog_match_accepted' => null,
            'catalog_match_bib_id'   => null,
            'isbndb_searched'        => false,
            'isbndb_result_count'    => null,
            'isbndb_match_accepted'  => null,
            'is_duplicate'           => false,
            'duplicate_of_request_id'=> null,
            'assigned_to_user_id'    => null,
            'assigned_at'            => null,
            'assigned_by_user_id'    => null,
        ];
    }

    // ── States ────────────────────────────────────────────────────────────────

    /** ILL request. */
    public function ill(): static
    {
        return $this->state([
            'request_kind' => 'ill',
        ]);
    }

    /**
     * Simulate a catalog search that found results.
     *
     * @param bool $accepted  Whether the patron accepted a catalog match.
     */
    public function catalogSearched(bool $accepted = false, ?string $bibId = null): static
    {
        return $this->state([
            'catalog_searched'       => true,
            'catalog_result_count'   => $this->faker->numberBetween(1, 25),
            'catalog_match_accepted' => $accepted,
            'catalog_match_bib_id'   => $accepted ? ($bibId ?? $this->faker->numerify('MWT######')) : null,
        ]);
    }

    /** Submitted recently (within the last 30 days — counts toward limit window). */
    public function recent(): static
    {
        return $this->state(function () {
            return ['created_at' => $this->faker->dateTimeBetween('-29 days', 'now')];
        });
    }

    /** Submitted before the current limit window (does not count toward limit). */
    public function older(): static
    {
        return $this->state(function () {
            return ['created_at' => $this->faker->dateTimeBetween('-180 days', '-31 days')];
        });
    }

    /** Mark as a duplicate of another request. */
    public function duplicate(int $ofRequestId): static
    {
        return $this->state([
            'is_duplicate'            => true,
            'duplicate_of_request_id' => $ofRequestId,
        ]);
    }
}

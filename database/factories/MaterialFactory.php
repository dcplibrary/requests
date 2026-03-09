<?php

namespace Dcplibrary\Sfp\Database\Factories;

use Dcplibrary\Sfp\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for material (bibliographic) records.
 *
 * Default state: randomly generated fake title/author, source=submitted.
 * States: fromIsbndb(), realBook($index), fakeBook($index).
 *
 * A curated list of real recent titles and entirely fictional ones are
 * embedded here for dev seeding — select them by index or shuffle randomly.
 *
 * Usage:
 *   MaterialFactory::new()->create()
 *   MaterialFactory::new()->fromIsbndb()->create()
 *   MaterialFactory::new()->realBook(0)->create()
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    // ── Curated real titles (mix of recent bestsellers and classics) ──────────

    public const REAL_BOOKS = [
        ['title' => 'Demon Copperhead',                        'author' => 'Barbara Kingsolver',     'publish_date' => '2022', 'publisher' => 'Harper',            'isbn13' => '9780062741288'],
        ['title' => 'Tomorrow, and Tomorrow, and Tomorrow',    'author' => 'Gabrielle Zevin',         'publish_date' => '2022', 'publisher' => 'Knopf',             'isbn13' => '9780593321201'],
        ['title' => 'Fourth Wing',                             'author' => 'Rebecca Yarros',          'publish_date' => '2023', 'publisher' => 'Red Tower Books',   'isbn13' => '9781649374042'],
        ['title' => 'The Women',                               'author' => 'Kristin Hannah',          'publish_date' => '2024', 'publisher' => 'St. Martin\'s Press','isbn13' => '9781250178633'],
        ['title' => 'James',                                   'author' => 'Percival Everett',        'publish_date' => '2024', 'publisher' => 'Doubleday',         'isbn13' => '9780385550369'],
        ['title' => 'Orbital',                                 'author' => 'Samantha Harvey',         'publish_date' => '2023', 'publisher' => 'Grove Press',       'isbn13' => '9780802164490'],
        ['title' => 'The Covenant of Water',                   'author' => 'Abraham Verghese',        'publish_date' => '2023', 'publisher' => 'Grove Press',       'isbn13' => '9780802162175'],
        ['title' => 'All Fours',                               'author' => 'Miranda July',            'publish_date' => '2024', 'publisher' => 'Riverhead Books',   'isbn13' => '9780593713105'],
        ['title' => 'Intermezzo',                              'author' => 'Sally Rooney',            'publish_date' => '2024', 'publisher' => 'Farrar, Straus',    'isbn13' => '9780374609566'],
        ['title' => 'Slow Horses',                             'author' => 'Mick Herron',             'publish_date' => '2010', 'publisher' => 'Soho Crime',        'isbn13' => '9781616951207'],
        ['title' => 'The Thursday Murder Club',                'author' => 'Richard Osman',           'publish_date' => '2020', 'publisher' => 'Pamela Dorman Books','isbn13' => '9780525557319'],
        ['title' => 'Project Hail Mary',                       'author' => 'Andy Weir',               'publish_date' => '2021', 'publisher' => 'Ballantine Books',  'isbn13' => '9780593135204'],
        ['title' => 'Normal People',                           'author' => 'Sally Rooney',            'publish_date' => '2018', 'publisher' => 'Faber & Faber',     'isbn13' => '9780571334650'],
        ['title' => 'The Handmaid\'s Tale',                    'author' => 'Margaret Atwood',         'publish_date' => '1985', 'publisher' => 'McClelland & Stewart','isbn13' => '9780771008795'],
    ];

    // ── Fictional titles for test data ────────────────────────────────────────

    public const FAKE_BOOKS = [
        ['title' => 'Whispering Embers',              'author' => 'Cora Blackwood',     'publish_date' => '2023'],
        ['title' => 'The Last Algorithm',             'author' => 'Marcus Trent',       'publish_date' => '2024'],
        ['title' => 'Daughters of the Red Sea',       'author' => 'Fatima Yusuf',       'publish_date' => '2022'],
        ['title' => 'Steel and Sorrow',               'author' => 'Derek Okafor',       'publish_date' => '2023'],
        ['title' => 'The Quiet Inheritance',          'author' => 'Sasha Novak',        'publish_date' => '2024'],
        ['title' => 'Midnight in the Catalog',        'author' => 'Jane Holloway',      'publish_date' => '2021'],
        ['title' => 'Borrowed Light',                 'author' => 'Thomas Pemberton',   'publish_date' => '2023'],
        ['title' => 'The Glass Meridian',             'author' => 'Elena Roux',         'publish_date' => '2022'],
        ['title' => 'A Country of Small Rains',       'author' => 'Kwame Asante',       'publish_date' => '2024'],
        ['title' => 'Everything After Tuesday',       'author' => 'Piper Marsh',        'publish_date' => '2023'],
        ['title' => 'The Iron Cartographer',          'author' => 'Luca Ferreira',      'publish_date' => '2024'],
        ['title' => 'Seven Moons Over Duluth',        'author' => 'Rachel Hesse',       'publish_date' => '2022'],
        ['title' => 'Bright Particular Star',         'author' => 'James Obi',          'publish_date' => '2023'],
        ['title' => 'Saltwater Gospel',               'author' => 'Nadia Vance',        'publish_date' => '2021'],
        ['title' => 'The Peripheral Wife',            'author' => 'Connor Daly',        'publish_date' => '2024'],
    ];

    // ── Definition ────────────────────────────────────────────────────────────

    public function definition(): array
    {
        $all   = array_merge(self::REAL_BOOKS, self::FAKE_BOOKS);
        $entry = $this->faker->randomElement($all);

        return [
            'title'            => $entry['title'],
            'author'           => $entry['author'],
            'publish_date'     => $entry['publish_date'] ?? $this->faker->year(),
            'isbn'             => $entry['isbn'] ?? null,
            'isbn13'           => $entry['isbn13'] ?? null,
            'publisher'        => $entry['publisher'] ?? null,
            'exact_publish_date' => null,
            'edition'          => null,
            'overview'         => null,
            'source'           => 'submitted',
            'material_type_id' => null,
        ];
    }

    // ── States ────────────────────────────────────────────────────────────────

    /** Use a specific real book by index into REAL_BOOKS. */
    public function realBook(int $index = 0): static
    {
        $entry = self::REAL_BOOKS[$index % count(self::REAL_BOOKS)];

        return $this->state([
            'title'        => $entry['title'],
            'author'       => $entry['author'],
            'publish_date' => $entry['publish_date'],
            'isbn13'       => $entry['isbn13'] ?? null,
            'publisher'    => $entry['publisher'] ?? null,
            'source'       => 'isbndb',
        ]);
    }

    /** Use a specific fake book by index into FAKE_BOOKS. */
    public function fakeBook(int $index = 0): static
    {
        $entry = self::FAKE_BOOKS[$index % count(self::FAKE_BOOKS)];

        return $this->state([
            'title'        => $entry['title'],
            'author'       => $entry['author'],
            'publish_date' => $entry['publish_date'],
            'isbn13'       => null,
            'publisher'    => null,
            'source'       => 'submitted',
        ]);
    }

    /** Mark as enriched from ISBNdb (usually means isbn13 and publisher are set). */
    public function fromIsbndb(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'source'  => 'isbndb',
                'isbn13'  => $attributes['isbn13'] ?? $this->faker->numerify('978#########'),
                'publisher' => $attributes['publisher'] ?? $this->faker->company(),
            ];
        });
    }
}

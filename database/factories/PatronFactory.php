<?php

namespace Dcplibrary\Sfp\Database\Factories;

use Dcplibrary\Sfp\Models\Patron;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for patron records.
 *
 * Default state: submitted info, Polaris lookup not yet attempted.
 * States: foundInPolaris(), notFoundInPolaris(), lookupPending().
 *
 * Usage:
 *   PatronFactory::new()->create()
 *   PatronFactory::new()->foundInPolaris()->create()
 */
class PatronFactory extends Factory
{
    protected $model = Patron::class;

    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName  = $this->faker->lastName();

        // Realistic library card barcode: 14-digit number starting with 2
        $barcode = '2' . $this->faker->numerify(str_repeat('#', 13));

        return [
            'barcode'                  => $barcode,
            'name_first'               => $firstName,
            'name_last'                => $lastName,
            'phone'                    => $this->faker->numerify('###-###-####'),
            'email'                    => $this->faker->optional(0.85)->safeEmail(),
            'found_in_polaris'         => false,
            'polaris_lookup_attempted' => false,
            'polaris_lookup_at'        => null,
            'polaris_patron_id'        => null,
            'polaris_patron_code_id'   => null,
            'polaris_name_first'       => null,
            'polaris_name_last'        => null,
            'polaris_phone'            => null,
            'polaris_email'            => null,
            'name_first_matches'       => null,
            'name_last_matches'        => null,
            'phone_matches'            => null,
            'email_matches'            => null,
            'preferred_phone'          => 'submitted',
            'preferred_email'          => 'submitted',
        ];
    }

    /**
     * Patron was found in Polaris. Populates Polaris fields with slight
     * variations to simulate real-world ILS data (minor formatting diffs).
     */
    public function foundInPolaris(): static
    {
        return $this->state(function (array $attributes) {
            $firstMatches = $this->faker->boolean(90);
            $lastMatches  = $this->faker->boolean(95);
            $phoneMatches = $this->faker->boolean(80);
            $emailMatches = $this->faker->boolean(85);

            return [
                'found_in_polaris'         => true,
                'polaris_lookup_attempted' => true,
                'polaris_lookup_at'        => now()->subHours($this->faker->numberBetween(1, 72)),
                'polaris_patron_id'        => $this->faker->numberBetween(100000, 999999),
                'polaris_patron_code_id'   => $this->faker->randomElement([1, 2, 3, 5]),
                'polaris_name_first'       => $firstMatches ? $attributes['name_first'] : $this->faker->firstName(),
                'polaris_name_last'        => $lastMatches  ? $attributes['name_last']  : $this->faker->lastName(),
                'polaris_phone'            => $phoneMatches ? $attributes['phone'] : $this->faker->numerify('###-###-####'),
                'polaris_email'            => $emailMatches ? $attributes['email'] : $this->faker->safeEmail(),
                'name_first_matches'       => $firstMatches,
                'name_last_matches'        => $lastMatches,
                'phone_matches'            => $phoneMatches,
                'email_matches'            => $emailMatches,
            ];
        });
    }

    /** Patron was not found in Polaris. */
    public function notFoundInPolaris(): static
    {
        return $this->state([
            'found_in_polaris'         => false,
            'polaris_lookup_attempted' => true,
            'polaris_lookup_at'        => now()->subHours($this->faker->numberBetween(1, 48)),
        ]);
    }

    /** Polaris lookup has not been attempted yet (e.g. job queued but not run). */
    public function lookupPending(): static
    {
        return $this->state([
            'found_in_polaris'         => false,
            'polaris_lookup_attempted' => false,
            'polaris_lookup_at'        => null,
        ]);
    }
}

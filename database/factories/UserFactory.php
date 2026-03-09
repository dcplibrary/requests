<?php

namespace Dcplibrary\Sfp\Database\Factories;

use Dcplibrary\Sfp\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for sfp_users staff accounts.
 *
 * Default state: active selector.
 * States: admin(), ill(), inactive().
 *
 * Usage:
 *   UserFactory::new()->create()
 *   UserFactory::new()->admin()->create()
 *   UserFactory::new()->count(3)->create()
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        $firstName = $this->faker->firstName();
        $lastName  = $this->faker->lastName();

        return [
            'name'          => "{$firstName} {$lastName}",
            'email'         => strtolower("{$firstName}.{$lastName}@dcplibrary.org"),
            'entra_id'      => $this->faker->uuid(),
            'role'          => 'selector',
            'active'        => true,
            'last_login_at' => $this->faker->optional(0.8)->dateTimeThisYear(),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => 'admin']);
    }

    /**
     * User with selector role (ILL access is via group membership, not role;
     * use hasIllAccess() or attach to the ill_selector_group_id group).
     */
    public function ill(): static
    {
        return $this->state(['role' => 'selector']);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false, 'last_login_at' => null]);
    }
}

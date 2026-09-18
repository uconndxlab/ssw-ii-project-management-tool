<?php

namespace Database\Factories;

use App\Models\State;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<State>
 *
 * Do not combine with StateSeeder in the same test — Faker provides 50 US states
 * while StateSeeder inserts 59 rows, and both name and code are unique.
 * Look up seeded states with State::firstWhere('code', 'KS') instead.
 */
class StateFactory extends Factory
{
    protected $model = State::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->state(),
            'code' => fake()->unique()->stateAbbr(),
            'is_territory' => false,
        ];
    }

    public function territory(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_territory' => true,
        ]);
    }
}

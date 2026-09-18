<?php

namespace Database\Factories;

use App\Models\KfsAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<KfsAccount> */
class KfsAccountFactory extends Factory
{
    protected $model = KfsAccount::class;

    public function definition(): array
    {
        return [
            'number' => fake()->unique()->numerify('#######'),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Enums\RecentRecordType;
use App\Models\Agreement;
use App\Models\RecentRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecentRecord>
 */
class RecentRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'record_type' => RecentRecordType::Agreement,
            'record_id' => Agreement::factory(),
            'last_opened_at' => now(),
        ];
    }
}

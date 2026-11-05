<?php

namespace Database\Factories;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use App\Models\Agreement;
use App\Models\ReportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportRequest>
 */
class ReportRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agreement_id' => Agreement::factory(),
            'user_id' => User::factory(),
            'user_name' => fn (array $attributes) => User::findOrFail($attributes['user_id'])->name,
            'user_email' => fn (array $attributes) => User::findOrFail($attributes['user_id'])->email,
            'type' => ReportType::BasicActivityExport,
            'format' => ReportFormat::Csv,
        ];
    }
}

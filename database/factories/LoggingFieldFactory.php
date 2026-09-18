<?php

namespace Database\Factories;

use App\Enums\ProgramScopeMode;
use App\Models\LoggingField;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LoggingField> */
class LoggingFieldFactory extends Factory
{
    protected $model = LoggingField::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => $name,
            'slug' => fake()->unique()->slug(),
            'field_type' => 'text',
            'help_text' => fake()->optional()->sentence(),
            'options_json' => null,
            'is_active' => true,
            'sort_order' => null,
            'is_full_width' => false,
            'available_in_agreements' => false,
            'available_in_contact_families' => false,
            'available_in_activities' => false,
            'program_scope_mode' => ProgramScopeMode::All,
        ];
    }

    public function forAgreements(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_in_agreements' => true,
        ]);
    }

    public function forContactFamilies(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_in_contact_families' => true,
        ]);
    }

    public function forActivities(): static
    {
        return $this->state(fn (array $attributes) => [
            'available_in_activities' => true,
        ]);
    }

    /**
     * @param  array<int, string>  $options
     */
    public function select(array $options): static
    {
        return $this->state(fn (array $attributes) => [
            'field_type' => LoggingField::FIELD_TYPE_SELECT,
            'options_json' => $options,
        ]);
    }
}

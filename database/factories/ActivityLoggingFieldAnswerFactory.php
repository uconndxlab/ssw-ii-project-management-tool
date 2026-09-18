<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ActivityLoggingFieldAnswer;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLoggingFieldAnswer>
 *
 * context_id has no foreign key; its referent depends on context_type.
 * Use forAgreement(), forContactFamily(), or forActivityType() when the
 * default activity_type context is not appropriate.
 */
class ActivityLoggingFieldAnswerFactory extends Factory
{
    protected $model = ActivityLoggingFieldAnswer::class;

    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'logging_field_id' => LoggingField::factory()->forActivities(),
            'context_type' => 'activity_type',
            'context_id' => 0,
            'value_text' => fake()->sentence(),
            'value_json' => null,
            'value_number' => null,
            'value_boolean' => null,
            'file_path' => null,
            'file_name' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ActivityLoggingFieldAnswer $answer) {
            if ($answer->context_type !== 'activity_type' || $answer->context_id !== 0) {
                return;
            }

            if ($answer->activity_id !== null) {
                $activity = Activity::query()->find($answer->activity_id);
                if ($activity !== null) {
                    $answer->context_id = $activity->activity_type_id;

                    return;
                }
            }
        });
    }

    public function forAgreement(Agreement $agreement): static
    {
        return $this->state(fn (array $attributes) => [
            'context_type' => 'agreement',
            'context_id' => $agreement->id,
        ]);
    }

    public function forContactFamily(ContactFamily $contactFamily): static
    {
        return $this->state(fn (array $attributes) => [
            'context_type' => 'contact_family',
            'context_id' => $contactFamily->id,
        ]);
    }

    public function forActivityType(ActivityType $activityType): static
    {
        return $this->state(fn (array $attributes) => [
            'context_type' => 'activity_type',
            'context_id' => $activityType->id,
        ]);
    }

    public function text(?string $value = null): static
    {
        return $this->state(fn (array $attributes) => [
            'value_text' => $value ?? fake()->sentence(),
            'value_json' => null,
            'value_number' => null,
            'value_boolean' => null,
            'file_path' => null,
            'file_name' => null,
        ]);
    }

    public function number(float $value): static
    {
        return $this->state(fn (array $attributes) => [
            'value_text' => null,
            'value_json' => null,
            'value_number' => $value,
            'value_boolean' => null,
            'file_path' => null,
            'file_name' => null,
        ]);
    }

    public function boolean(bool $value = true): static
    {
        return $this->state(fn (array $attributes) => [
            'value_text' => null,
            'value_json' => null,
            'value_number' => null,
            'value_boolean' => $value,
            'file_path' => null,
            'file_name' => null,
        ]);
    }

    /**
     * @param  array<int, string>  $values
     */
    public function multiselect(array $values): static
    {
        return $this->state(fn (array $attributes) => [
            'value_text' => null,
            'value_json' => $values,
            'value_number' => null,
            'value_boolean' => null,
            'file_path' => null,
            'file_name' => null,
        ]);
    }

    public function document(string $path, ?string $fileName = null): static
    {
        return $this->state(fn (array $attributes) => [
            'value_text' => null,
            'value_json' => null,
            'value_number' => null,
            'value_boolean' => null,
            'file_path' => $path,
            'file_name' => $fileName ?? basename($path),
        ]);
    }
}

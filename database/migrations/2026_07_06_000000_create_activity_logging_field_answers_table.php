<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_logging_field_answers')) {
            Schema::create('activity_logging_field_answers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
                $table->foreignId('logging_field_id')->constrained('logging_fields')->cascadeOnDelete();
                $table->string('context_type');
                $table->unsignedBigInteger('context_id');
                $table->text('value_text')->nullable();
                $table->decimal('value_number', 15, 4)->nullable();
                $table->boolean('value_boolean')->nullable();
                $table->string('file_path')->nullable();
                $table->timestamps();

                $table->unique(['activity_id', 'logging_field_id', 'context_type', 'context_id'], 'activity_logging_answers_unique');
                $table->index(['logging_field_id', 'context_type', 'context_id'], 'activity_logging_answers_reporting_idx');
            });
        }

        if (Schema::hasColumn('activities', 'logging_field_data') && DB::table('activity_logging_field_answers')->count() === 0) {
            $fieldTypes = DB::table('logging_fields')->pluck('field_type', 'id');

            DB::table('activities')
                ->leftJoin('activity_types', 'activities.activity_type_id', '=', 'activity_types.id')
                ->select('activities.id as id', 'activities.logging_field_data', 'activity_types.contact_family_id')
                ->chunkById(100, function ($activities) use ($fieldTypes) {
                    $rows = [];

                    foreach ($activities as $activity) {
                        $data = json_decode($activity->logging_field_data ?? 'null', true);
                        if (!is_array($data)) {
                            continue;
                        }

                        foreach (($data['agreements'] ?? []) as $agreementId => $fields) {
                            if (!is_array($fields)) {
                                continue;
                            }

                            foreach ($fields as $fieldId => $value) {
                                $row = $this->buildAnswerRow(
                                    (int) $activity->id,
                                    (int) $fieldId,
                                    'agreement',
                                    (int) $agreementId,
                                    $fieldTypes[(int) $fieldId] ?? null,
                                    $value
                                );

                                if ($row !== null) {
                                    $rows[] = $row;
                                }
                            }
                        }

                        foreach (($data['contact_family'] ?? []) as $fieldId => $value) {
                            $row = $this->buildAnswerRow(
                                (int) $activity->id,
                                (int) $fieldId,
                                'contact_family',
                                (int) $activity->contact_family_id,
                                $fieldTypes[(int) $fieldId] ?? null,
                                $value
                            );

                            if ($row !== null) {
                                $rows[] = $row;
                            }
                        }
                    }

                    if (!empty($rows)) {
                        DB::table('activity_logging_field_answers')->insert($rows);
                    }
                });
        }

        if (Schema::hasColumn('activities', 'logging_field_data')) {
            Schema::table('activities', function (Blueprint $table) {
                $table->dropColumn('logging_field_data');
            });
        }
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->json('logging_field_data')->nullable();
        });

        $activities = DB::table('activities')->pluck('id');

        foreach ($activities as $activityId) {
            $answers = DB::table('activity_logging_field_answers')
                ->where('activity_id', $activityId)
                ->get();

            $payload = [
                'agreements' => [],
                'contact_family' => [],
            ];

            foreach ($answers as $answer) {
                $value = $answer->file_path;

                if ($answer->value_boolean !== null) {
                    $value = (bool) $answer->value_boolean;
                } elseif ($answer->value_number !== null) {
                    $value = (float) $answer->value_number;
                } elseif ($answer->value_text !== null) {
                    $value = $answer->value_text;
                }

                if ($answer->context_type === 'agreement') {
                    $payload['agreements'][$answer->context_id][$answer->logging_field_id] = $value;
                    continue;
                }

                $payload['contact_family'][$answer->logging_field_id] = $value;
            }

            DB::table('activities')
                ->where('id', $activityId)
                ->update(['logging_field_data' => json_encode($payload)]);
        }

        Schema::dropIfExists('activity_logging_field_answers');
    }

    private function buildAnswerRow(int $activityId, int $fieldId, string $contextType, int $contextId, ?string $fieldType, mixed $value): ?array
    {
        if ($fieldId === 0 || $contextId === 0 || $fieldType === null) {
            return null;
        }

        $row = [
            'activity_id' => $activityId,
            'logging_field_id' => $fieldId,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'value_text' => null,
            'value_number' => null,
            'value_boolean' => null,
            'file_path' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return match ($fieldType) {
            'number', 'decimal' => $value === null || $value === '' ? null : array_merge($row, ['value_number' => (float) $value]),
            'checkbox' => array_merge($row, ['value_boolean' => (bool) $value]),
            'document' => $value ? array_merge($row, ['file_path' => (string) $value]) : null,
            default => $value === null || $value === '' ? null : array_merge($row, ['value_text' => (string) $value]),
        };
    }
};

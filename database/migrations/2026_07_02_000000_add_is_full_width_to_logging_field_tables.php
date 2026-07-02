<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('logging_fields', function (Blueprint $table) {
            $table->boolean('is_full_width')->default(false)->after('sort_order');
            $table->boolean('available_in_agreements')->default(false)->after('is_full_width');
            $table->boolean('available_in_contact_families')->default(false)->after('available_in_agreements');
            $table->boolean('available_in_activities')->default(false)->after('available_in_contact_families');
        });

        Schema::table('agreement_logging_field_assignments', function (Blueprint $table) {
            $table->foreignId('logging_field_id')->nullable()->after('agreement_logging_field_id')->constrained('logging_fields')->cascadeOnDelete();
        });

        Schema::table('contact_family_logging_field_assignments', function (Blueprint $table) {
            $table->foreignId('logging_field_id')->nullable()->after('contact_family_logging_field_id')->constrained('logging_fields')->cascadeOnDelete();
        });

        DB::transaction(function () {
            $agreementFieldMap = $this->migrateFields('agreement_logging_fields', 'available_in_agreements');
            $contactFamilyFieldMap = $this->migrateFields('contact_family_logging_fields', 'available_in_contact_families');

            foreach (DB::table('agreement_logging_field_assignments')->orderBy('id')->get() as $assignment) {
                $loggingFieldId = $agreementFieldMap[$assignment->agreement_logging_field_id] ?? null;
                if ($loggingFieldId) {
                    DB::table('agreement_logging_field_assignments')
                        ->where('id', $assignment->id)
                        ->update(['logging_field_id' => $loggingFieldId]);
                }
            }

            foreach (DB::table('contact_family_logging_field_assignments')->orderBy('id')->get() as $assignment) {
                $loggingFieldId = $contactFamilyFieldMap[$assignment->contact_family_logging_field_id] ?? null;
                if ($loggingFieldId) {
                    DB::table('contact_family_logging_field_assignments')
                        ->where('id', $assignment->id)
                        ->update(['logging_field_id' => $loggingFieldId]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('logging_fields', function (Blueprint $table) {
            $table->dropColumn([
                'available_in_activities',
                'available_in_contact_families',
                'available_in_agreements',
                'is_full_width',
            ]);
        });

        Schema::table('agreement_logging_field_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('logging_field_id');
        });

        Schema::table('contact_family_logging_field_assignments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('logging_field_id');
        });
    }

    private function migrateFields(string $sourceTable, string $availabilityColumn): array
    {
        $fieldMap = [];

        foreach (DB::table($sourceTable)->orderBy('sort_order')->orderBy('name')->get() as $field) {
            $existing = DB::table('logging_fields')->where('slug', $field->slug)->first();

            $payload = [
                'name' => $field->name,
                'slug' => $field->slug,
                'field_type' => $field->field_type,
                'help_text' => $field->help_text,
                'options_json' => $field->options_json,
                'is_active' => (bool) $field->is_active,
                'sort_order' => $field->sort_order,
                'is_full_width' => (bool) ($field->is_full_width ?? false),
                'available_in_agreements' => false,
                'available_in_contact_families' => false,
                'available_in_activities' => false,
                'created_at' => $field->created_at,
                'updated_at' => $field->updated_at,
            ];

            $payload[$availabilityColumn] = true;

            if ($existing) {
                $payload['available_in_agreements'] = (bool) $existing->available_in_agreements || $payload['available_in_agreements'];
                $payload['available_in_contact_families'] = (bool) $existing->available_in_contact_families || $payload['available_in_contact_families'];
                $payload['available_in_activities'] = (bool) $existing->available_in_activities || $payload['available_in_activities'];

                DB::table('logging_fields')->where('id', $existing->id)->update([
                    'name' => $payload['name'],
                    'slug' => $payload['slug'],
                    'field_type' => $payload['field_type'],
                    'help_text' => $payload['help_text'],
                    'options_json' => $payload['options_json'],
                    'is_active' => $payload['is_active'],
                    'sort_order' => $payload['sort_order'],
                    'is_full_width' => $payload['is_full_width'],
                    'available_in_agreements' => $payload['available_in_agreements'],
                    'available_in_contact_families' => $payload['available_in_contact_families'],
                    'available_in_activities' => $payload['available_in_activities'],
                    'updated_at' => $field->updated_at,
                ]);

                $fieldMap[$field->id] = $existing->id;
                continue;
            }

            $fieldMap[$field->id] = DB::table('logging_fields')->insertGetId($payload);
        }

        return $fieldMap;
    }
};

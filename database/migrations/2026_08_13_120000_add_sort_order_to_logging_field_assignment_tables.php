<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreement_logging_field_assignments', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_required');
        });

        Schema::table('contact_family_logging_field_assignments', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_required');
        });

        Schema::table('activity_type_logging_field_assignments', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->after('is_required');
        });

        DB::transaction(function () {
            foreach ([
                'agreement_logging_field_assignments' => ['owner' => 'agreement_id', 'field' => 'logging_field_id'],
                'contact_family_logging_field_assignments' => ['owner' => 'contact_family_id', 'field' => 'logging_field_id'],
                'activity_type_logging_field_assignments' => ['owner' => 'activity_type_id', 'field' => 'logging_field_id'],
            ] as $table => $columns) {
                DB::table($table)
                    ->orderBy($columns['owner'])
                    ->orderBy($columns['field'])
                    ->orderBy('id')
                    ->get()
                    ->groupBy($columns['owner'])
                    ->each(function ($rows) use ($table) {
                        foreach ($rows->values() as $index => $row) {
                            DB::table($table)
                                ->where('id', $row->id)
                                ->update(['sort_order' => $index + 1]);
                        }
                    });
            }
        });
    }

    public function down(): void
    {
        Schema::table('agreement_logging_field_assignments', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('contact_family_logging_field_assignments', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });

        Schema::table('activity_type_logging_field_assignments', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreement_deliverables', function (Blueprint $table) {
            if (!Schema::hasColumn('agreement_deliverables', 'time_basis')) {
                $table->string('time_basis')->default('observed')->after('metric_type');
            }
        });

        Schema::table('activities', function (Blueprint $table) {
            if (!Schema::hasColumn('activities', 'completion_count')) {
                $table->unsignedInteger('completion_count')->default(1)->after('activity_type_id');
            }
            if (!Schema::hasColumn('activities', 'allotted_duration_hours')) {
                $table->decimal('allotted_duration_hours', 8, 1)->nullable()->after('completion_count');
            }
            if (!Schema::hasColumn('activities', 'allotted_duration_days')) {
                $table->decimal('allotted_duration_days', 8, 1)->nullable()->after('allotted_duration_hours');
            }
        });

        Schema::table('agreement_activity_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('agreement_activity_histories', 'allotted_hours')) {
                $table->decimal('allotted_hours', 10, 2)->nullable()->after('follow_up_hours');
            }
            if (!Schema::hasColumn('agreement_activity_histories', 'allotted_days')) {
                $table->decimal('allotted_days', 10, 2)->nullable()->after('allotted_hours');
            }
        });

        Schema::table('deliverable_contributions', function (Blueprint $table) {
            if (!Schema::hasColumn('deliverable_contributions', 'credited_allotted_hours')) {
                $table->decimal('credited_allotted_hours', 10, 2)->nullable()->after('credited_hours');
            }
            if (!Schema::hasColumn('deliverable_contributions', 'credited_allotted_days')) {
                $table->decimal('credited_allotted_days', 10, 2)->nullable()->after('credited_allotted_hours');
            }
        });

        DB::table('agreement_deliverables')
            ->where('metric_type', 'time')
            ->whereNull('time_basis')
            ->update(['time_basis' => 'observed']);
    }

    public function down(): void
    {
        Schema::table('deliverable_contributions', function (Blueprint $table) {
            $table->dropColumn(['credited_allotted_hours', 'credited_allotted_days']);
        });

        Schema::table('agreement_activity_histories', function (Blueprint $table) {
            $table->dropColumn(['allotted_hours', 'allotted_days']);
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['completion_count', 'allotted_duration_hours', 'allotted_duration_days']);
        });

        Schema::table('agreement_deliverables', function (Blueprint $table) {
            $table->dropColumn('time_basis');
        });
    }
};

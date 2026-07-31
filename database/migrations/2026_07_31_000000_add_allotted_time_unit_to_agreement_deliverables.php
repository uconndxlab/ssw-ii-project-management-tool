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
            if (!Schema::hasColumn('agreement_deliverables', 'allotted_time_unit')) {
                $table->string('allotted_time_unit')->nullable()->after('time_basis');
            }
        });

        $deliverables = DB::table('agreement_deliverables')
            ->where('metric_type', 'time')
            ->where('time_basis', 'allotted')
            ->whereNotNull('activity_type_id')
            ->get(['id', 'activity_type_id']);

        foreach ($deliverables as $deliverable) {
            $activityType = DB::table('activity_types')
                ->where('id', $deliverable->activity_type_id)
                ->first(['duration_days', 'duration_hours']);

            if (!$activityType) {
                continue;
            }

            $unit = (float) ($activityType->duration_days ?? 0) > 0
                ? 'days'
                : ((float) ($activityType->duration_hours ?? 0) > 0 ? 'hours' : null);

            if ($unit) {
                DB::table('agreement_deliverables')
                    ->where('id', $deliverable->id)
                    ->update(['allotted_time_unit' => $unit]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('agreement_deliverables', function (Blueprint $table) {
            $table->dropColumn('allotted_time_unit');
        });
    }
};

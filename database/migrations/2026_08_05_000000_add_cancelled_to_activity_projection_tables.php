<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            if (!Schema::hasColumn('activities', 'cancelled')) {
                $table->boolean('cancelled')->default(false)->after('internal_only');
            }
        });

        Schema::table('agreement_activity_histories', function (Blueprint $table) {
            if (!Schema::hasColumn('agreement_activity_histories', 'cancelled')) {
                $table->boolean('cancelled')->default(false)->after('team_ids_snapshot');
            }
        });

        Schema::table('deliverable_contributions', function (Blueprint $table) {
            if (!Schema::hasColumn('deliverable_contributions', 'cancelled')) {
                $table->boolean('cancelled')->default(false)->after('rules_fingerprint');
            }
        });

        $activityCancelledById = DB::table('activities')
            ->pluck('cancelled', 'id');

        DB::table('agreement_activity_histories')
            ->select(['id', 'activity_id'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($activityCancelledById) {
                foreach ($rows as $row) {
                    DB::table('agreement_activity_histories')
                        ->where('id', $row->id)
                        ->update([
                            'cancelled' => (bool) ($activityCancelledById[$row->activity_id] ?? false),
                        ]);
                }
            });

        DB::table('deliverable_contributions')
            ->select(['id', 'activity_id'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) use ($activityCancelledById) {
                foreach ($rows as $row) {
                    DB::table('deliverable_contributions')
                        ->where('id', $row->id)
                        ->update([
                            'cancelled' => (bool) ($activityCancelledById[$row->activity_id] ?? false),
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('deliverable_contributions', function (Blueprint $table) {
            if (Schema::hasColumn('deliverable_contributions', 'cancelled')) {
                $table->dropColumn('cancelled');
            }
        });

        Schema::table('agreement_activity_histories', function (Blueprint $table) {
            if (Schema::hasColumn('agreement_activity_histories', 'cancelled')) {
                $table->dropColumn('cancelled');
            }
        });

        Schema::table('activities', function (Blueprint $table) {
            if (Schema::hasColumn('activities', 'cancelled')) {
                $table->dropColumn('cancelled');
            }
        });
    }
};

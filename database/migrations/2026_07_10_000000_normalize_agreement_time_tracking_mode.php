<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('agreements', 'time_tracking_mode')) {
            DB::table('agreements')
                ->where('time_tracking_mode', 'engagement')
                ->update(['time_tracking_mode' => null]);

            Schema::table('agreements', function (Blueprint $table) {
                $table->string('time_tracking_mode')->nullable()->default(null)->change();
            });

            return;
        }

        Schema::table('agreements', function (Blueprint $table) {
            $table->string('time_tracking_mode')->nullable()->default(null)->after('extension_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('agreements', 'time_tracking_mode')) {
            return;
        }

        Schema::table('agreements', function (Blueprint $table) {
            $table->string('time_tracking_mode')->nullable()->default('engagement')->change();
        });

        DB::table('agreements')
            ->whereNull('time_tracking_mode')
            ->update(['time_tracking_mode' => 'engagement']);
    }
};

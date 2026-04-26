<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->boolean('internal_only')->default(false)->after('follow_up');
            $table->string('time_tracking_mode')->nullable()->default('engagement')->after('internal_only')->comment('engagement, participant');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn(['internal_only', 'time_tracking_mode']);
        });
    }
};

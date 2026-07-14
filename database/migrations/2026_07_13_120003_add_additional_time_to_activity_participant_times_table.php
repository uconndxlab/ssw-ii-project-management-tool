<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_participant_times', function (Blueprint $table) {
            $table->decimal('prep_hours', 8, 2)->default(0)->after('hours');
            $table->decimal('follow_up_hours', 8, 2)->default(0)->after('prep_hours');
        });
    }

    public function down(): void
    {
        Schema::table('activity_participant_times', function (Blueprint $table) {
            $table->dropColumn(['prep_hours', 'follow_up_hours']);
        });
    }
};
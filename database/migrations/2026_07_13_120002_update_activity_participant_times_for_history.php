<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_participant_times', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('activity_participant_times', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('participant_name')->nullable()->after('user_id');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('activity_participant_times', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });

        Schema::table('activity_participant_times', function (Blueprint $table) {
            $table->dropColumn('participant_name');
            $table->foreignId('user_id')->nullable(false)->change();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
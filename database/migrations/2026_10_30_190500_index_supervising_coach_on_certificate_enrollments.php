<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Every activity list now filters on this column to find activities a user supervises evidence for.
    public function up(): void
    {
        Schema::table('certificate_enrollments', function (Blueprint $table) {
            $table->index('supervising_coach_user_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_enrollments', function (Blueprint $table) {
            $table->dropIndex(['supervising_coach_user_id']);
        });
    }
};

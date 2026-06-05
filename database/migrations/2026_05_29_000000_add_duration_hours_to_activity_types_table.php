<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->unsignedInteger('duration_hours')->default(0)->after('duration_days');
        });
    }

    public function down(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropColumn('duration_hours');
        });
    }
};

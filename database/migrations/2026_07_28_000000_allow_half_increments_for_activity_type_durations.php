<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->decimal('duration_days', 8, 1)->default(0)->change();
            $table->decimal('duration_hours', 8, 1)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->unsignedInteger('duration_days')->default(0)->change();
            $table->unsignedInteger('duration_hours')->default(0)->change();
        });
    }
};

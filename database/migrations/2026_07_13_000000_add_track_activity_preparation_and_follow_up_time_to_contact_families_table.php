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
        Schema::table('contact_families', function (Blueprint $table) {
            $table->boolean('track_additional_time')
                ->default(false)
                ->after('active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contact_families', function (Blueprint $table) {
            $table->dropColumn('track_additional_time');
        });
    }
};

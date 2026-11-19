<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional "out of" ceiling for Points score fields: when set, a submission records a whole number from 0 to max_points.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certification_instrument_score_fields', function (Blueprint $table) {
            $table->unsignedSmallInteger('max_points')->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('certification_instrument_score_fields', function (Blueprint $table) {
            $table->dropColumn('max_points');
        });
    }
};

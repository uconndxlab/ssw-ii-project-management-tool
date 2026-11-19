<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A training is delivered under one activity type (e.g. W101 sections on Wraparound 101). Tools stay unset.
     */
    public function up(): void
    {
        Schema::table('certification_instruments', function (Blueprint $table) {
            $table->foreignId('activity_type_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('certification_instruments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_type_id');
        });
    }
};

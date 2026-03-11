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
        Schema::table('agreements', function (Blueprint $table) {
            $table->text('abstract')->nullable()->after('state_id');
            $table->date('original_end_date')->nullable()->after('end_date');
            $table->date('extended_end_date')->nullable()->after('original_end_date');
            $table->text('certification_candidates')->nullable()->after('extended_end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn(['abstract', 'original_end_date', 'extended_end_date', 'certification_candidates']);
        });
    }
};

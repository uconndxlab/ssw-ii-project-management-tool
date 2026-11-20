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
        Schema::table('certificate_awards', function (Blueprint $table) {
            $table->boolean('manual_award')->default(false);
            $table->string('originally_awarded_by')->nullable();
            $table->boolean('expiry_overridden')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('certificate_awards', function (Blueprint $table) {
            $table->dropColumn(['manual_award', 'originally_awarded_by', 'expiry_overridden']);
        });
    }
};

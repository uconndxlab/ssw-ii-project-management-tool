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
            // Add new date fields for extensions
            $table->date('extension_start_date')->nullable()->after('end_date');
            $table->date('extension_end_date')->nullable()->after('extension_start_date');
            
            // Add time tracking mode (engagement or participant)
            $table->string('time_tracking_mode')->default('engagement')->after('activity_logging_config');
            
            // Drop old confusing date fields
            $table->dropColumn(['original_end_date', 'extended_end_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            // Restore old fields
            $table->date('original_end_date')->nullable()->after('end_date');
            $table->date('extended_end_date')->nullable()->after('original_end_date');
            
            // Drop new fields
            $table->dropColumn(['extension_start_date', 'extension_end_date', 'time_tracking_mode']);
        });
    }
};

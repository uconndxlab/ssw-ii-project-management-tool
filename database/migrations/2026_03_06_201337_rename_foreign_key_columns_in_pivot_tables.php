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
        // Rename columns in agreement_user pivot table
        Schema::table('agreement_user', function (Blueprint $table) {
            $table->renameColumn('project_id', 'agreement_id');
        });
        
        // Rename columns in activity_program pivot table
        Schema::table('activity_program', function (Blueprint $table) {
            $table->renameColumn('engagement_id', 'activity_id');
        });
        
        // Rename columns in activity_user pivot table
        Schema::table('activity_user', function (Blueprint $table) {
            $table->renameColumn('engagement_id', 'activity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the column renames
        Schema::table('agreement_user', function (Blueprint $table) {
            $table->renameColumn('agreement_id', 'project_id');
        });
        
        Schema::table('activity_program', function (Blueprint $table) {
            $table->renameColumn('activity_id', 'engagement_id');
        });
        
        Schema::table('activity_user', function (Blueprint $table) {
            $table->renameColumn('activity_id', 'engagement_id');
        });
    }
};

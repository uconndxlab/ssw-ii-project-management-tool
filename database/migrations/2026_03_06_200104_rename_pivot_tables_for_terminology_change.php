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
        // Rename project_user to agreement_user
        Schema::rename('project_user', 'agreement_user');
        
        // Rename engagement_program to activity_program
        Schema::rename('engagement_program', 'activity_program');
        
        // Rename engagement_user to activity_user
        Schema::rename('engagement_user', 'activity_user');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reverse the renames
        Schema::rename('agreement_user', 'project_user');
        Schema::rename('activity_program', 'engagement_program');
        Schema::rename('activity_user', 'engagement_user');
    }
};

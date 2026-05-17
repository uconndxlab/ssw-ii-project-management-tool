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
        // Drop old foreign key columns from agreements table
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropForeign(['organization_id']);
            $table->dropForeign(['state_id']);
            $table->dropColumn(['organization_id', 'state_id']);
        });

        // Drop old foreign key column from activities table
        Schema::table('activities', function (Blueprint $table) {
            $table->dropForeign(['agreement_id']);
            $table->dropColumn('agreement_id');
        });

        // Create agreement_organization pivot table
        Schema::create('agreement_organization', function (Blueprint $table) {
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['agreement_id', 'organization_id']);
        });

        // Create agreement_state pivot table
        Schema::create('agreement_state', function (Blueprint $table) {
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['agreement_id', 'state_id']);
        });

        // Create activity_agreement pivot table
        Schema::create('activity_agreement', function (Blueprint $table) {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['activity_id', 'agreement_id']);
        });

        // Create activity_organization pivot table
        Schema::create('activity_organization', function (Blueprint $table) {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['activity_id', 'organization_id']);
        });

        // Create activity_state pivot table
        Schema::create('activity_state', function (Blueprint $table) {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['activity_id', 'state_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop pivot tables
        Schema::dropIfExists('activity_state');
        Schema::dropIfExists('activity_organization');
        Schema::dropIfExists('activity_agreement');
        Schema::dropIfExists('agreement_state');
        Schema::dropIfExists('agreement_organization');

        // Restore foreign key columns to agreements table
        Schema::table('agreements', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('state_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
        });

        // Restore foreign key column to activities table
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('agreement_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }
};

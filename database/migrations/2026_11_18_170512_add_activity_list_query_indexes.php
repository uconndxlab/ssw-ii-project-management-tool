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
        Schema::table('activities', function (Blueprint $table) {
            $table->index(['engagement_date', 'id']);
            $table->index(['user_id']);
            $table->index(['activity_type_id']);
            $table->index(['sub_activity_id']);
        });

        Schema::table('activity_agreement', function (Blueprint $table) {
            $table->index(['agreement_id', 'activity_id']);
        });

        Schema::table('activity_organization', function (Blueprint $table) {
            $table->index(['organization_id', 'activity_id']);
        });

        Schema::table('activity_state', function (Blueprint $table) {
            $table->index(['state_id', 'activity_id']);
        });

        Schema::table('activity_user', function (Blueprint $table) {
            $table->index(['user_id', 'activity_id']);
        });

        Schema::table('activity_program', function (Blueprint $table) {
            $table->index(['activity_id', 'program_id']);
            $table->index(['program_id', 'activity_id']);
        });

        Schema::table('activity_review_requests', function (Blueprint $table) {
            $table->index(['coach_user_id']);
        });

        Schema::table('activity_certification_candidates', function (Blueprint $table) {
            $table->index(['certification_candidate_id']);
        });

        Schema::table('program_project', function (Blueprint $table) {
            $table->index(['project_id', 'program_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->dropIndex(['engagement_date', 'id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['activity_type_id']);
            $table->dropIndex(['sub_activity_id']);
        });

        Schema::table('activity_agreement', function (Blueprint $table) {
            $table->dropIndex(['agreement_id', 'activity_id']);
        });

        Schema::table('activity_organization', function (Blueprint $table) {
            $table->dropIndex(['organization_id', 'activity_id']);
        });

        Schema::table('activity_state', function (Blueprint $table) {
            $table->dropIndex(['state_id', 'activity_id']);
        });

        Schema::table('activity_user', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'activity_id']);
        });

        Schema::table('activity_program', function (Blueprint $table) {
            $table->dropIndex(['activity_id', 'program_id']);
            $table->dropIndex(['program_id', 'activity_id']);
        });

        Schema::table('activity_review_requests', function (Blueprint $table) {
            $table->dropIndex(['coach_user_id']);
        });

        Schema::table('activity_certification_candidates', function (Blueprint $table) {
            $table->dropIndex(['certification_candidate_id']);
        });

        Schema::table('program_project', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'program_id']);
        });
    }
};

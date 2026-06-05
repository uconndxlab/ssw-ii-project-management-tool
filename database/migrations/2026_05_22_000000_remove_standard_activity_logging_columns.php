<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove legacy standard-field columns from activities.
        // All data collection beyond Date/Agreement/Org/State/InternalOnly
        // is now handled via agreement-specific and contact-family logging fields.
        Schema::table('activities', function (Blueprint $table) {
            $table->dropColumn([
                'event_hours',
                'prep_hours',
                'followup_hours',
                'participant_count',
                'external_attendees',
                'summary',
                'follow_up',
                'strengths',
                'recommendations',
                'time_tracking_mode',
            ]);
        });

        // Remove activity_logging_config toggle system from agreements.
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn([
                'activity_logging_config',
                'time_tracking_mode',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('activities', function (Blueprint $table) {
            $table->decimal('event_hours', 8, 2)->nullable();
            $table->decimal('prep_hours', 8, 2)->nullable();
            $table->decimal('followup_hours', 8, 2)->nullable();
            $table->integer('participant_count')->nullable();
            $table->text('external_attendees')->nullable();
            $table->text('summary')->nullable();
            $table->text('follow_up')->nullable();
            $table->text('strengths')->nullable();
            $table->text('recommendations')->nullable();
            $table->string('time_tracking_mode')->default('engagement');
        });

        Schema::table('agreements', function (Blueprint $table) {
            $table->json('activity_logging_config')->nullable();
            $table->string('time_tracking_mode')->default('engagement');
        });
    }
};

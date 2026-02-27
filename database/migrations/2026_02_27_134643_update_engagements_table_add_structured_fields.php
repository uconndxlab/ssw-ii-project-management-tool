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
        Schema::table('engagements', function (Blueprint $table) {
            // Add new structured fields
            $table->string('activity_type')->after('engagement_date');
            $table->string('deliverable_bucket')->after('activity_type');
            $table->decimal('event_hours', 6, 2)->after('deliverable_bucket');
            $table->decimal('prep_hours', 6, 2)->default(0)->after('event_hours');
            $table->decimal('followup_hours', 6, 2)->default(0)->after('prep_hours');
            $table->integer('participant_count')->nullable()->after('followup_hours');
            $table->text('summary')->nullable()->after('participant_count');
            $table->text('follow_up')->nullable()->after('summary');
            $table->text('strengths')->nullable()->after('follow_up');
            $table->text('recommendations')->nullable()->after('strengths');
            
            // Drop legacy fields
            $table->dropColumn(['engagement_type', 'hours', 'notes']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('engagements', function (Blueprint $table) {
            // Restore legacy fields
            $table->string('engagement_type')->after('engagement_date');
            $table->decimal('hours', 5, 2)->after('engagement_type');
            $table->text('notes')->nullable()->after('hours');
            
            // Drop new structured fields
            $table->dropColumn([
                'activity_type',
                'deliverable_bucket',
                'event_hours',
                'prep_hours',
                'followup_hours',
                'participant_count',
                'summary',
                'follow_up',
                'strengths',
                'recommendations'
            ]);
        });
    }
};

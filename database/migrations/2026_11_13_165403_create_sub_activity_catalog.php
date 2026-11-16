<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sub_activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            $table->text('helper_text')->nullable();
            $table->boolean('active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->decimal('duration_days', 8, 1)->default(0);
            $table->decimal('duration_hours', 8, 1)->default(0);
            $table->string('program_scope_mode')->default('all');
            $table->timestamps();
            $table->unique(['activity_type_id', 'name']);
        });

        Schema::create('sub_activity_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['sub_activity_id', 'program_id']);
        });

        Schema::create('sub_activity_logging_field_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sub_activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logging_field_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['sub_activity_id', 'logging_field_id'], 'sub_activity_field_assignment_unique');
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('sub_activity_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('agreement_deliverables', function (Blueprint $table) {
            $table->foreignId('sub_activity_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('certificate_requirements', function (Blueprint $table) {
            $table->foreignId('sub_activity_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('agreement_activity_histories', function (Blueprint $table) {
            $table->foreignId('sub_activity_id')->nullable()->constrained()->nullOnDelete();
            $table->index(['agreement_id', 'contact_family_id', 'activity_type_id', 'sub_activity_id'], 'agreement_activity_histories_sub_context_idx');
        });
        Schema::table('logging_fields', function (Blueprint $table) {
            $table->boolean('available_in_sub_activities')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('logging_fields', function (Blueprint $table) {
            $table->dropColumn('available_in_sub_activities');
        });
        foreach (['activities', 'agreement_deliverables', 'certificate_requirements', 'agreement_activity_histories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                if ($table->getTable() === 'agreement_activity_histories') {
                    $table->dropIndex('agreement_activity_histories_sub_context_idx');
                }
                $table->dropConstrainedForeignId('sub_activity_id');
            });
        }
        Schema::dropIfExists('sub_activity_logging_field_assignments');
        Schema::dropIfExists('sub_activity_program');
        Schema::dropIfExists('sub_activities');
    }
};

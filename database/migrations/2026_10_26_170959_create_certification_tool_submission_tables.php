<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_tool_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_certification_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certification_tool_id')->constrained()->restrictOnDelete();
            $table->string('outcome');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('certification_tool_submission_dimension_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_tool_submission_id')->constrained(indexName: 'submission_value_submission_fk')->cascadeOnDelete();
            $table->foreignId('certification_tool_dimension_id')->constrained(indexName: 'submission_value_dimension_fk')->restrictOnDelete();
            $table->foreignId('certification_tool_dimension_option_id')->constrained(indexName: 'submission_value_option_fk')->restrictOnDelete();
            $table->unique(['certification_tool_submission_id', 'certification_tool_dimension_id'], 'submission_dimension_unique');
        });
        Schema::create('certification_tool_submission_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_tool_submission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certification_tool_score_field_id')->constrained()->restrictOnDelete();
            $table->decimal('value', 12, 4);
            $table->unique(['certification_tool_submission_id', 'certification_tool_score_field_id'], 'submission_score_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certification_tool_submission_scores');
        Schema::dropIfExists('certification_tool_submission_dimension_values');
        Schema::dropIfExists('certification_tool_submissions');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tools and trainings are both certification instruments. Rename only: rows, ids, keys and
 * constraints are kept (constraint/index names keep their original tool-based names).
 * Calls are literal (no loops) so static analysis can follow the schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('certification_tools', 'certification_instruments');
        Schema::rename('certification_tool_program', 'certification_instrument_program');
        Schema::rename('certification_tool_dimensions', 'certification_instrument_dimensions');
        Schema::rename('certification_tool_dimension_options', 'certification_instrument_dimension_options');
        Schema::rename('certification_tool_score_fields', 'certification_instrument_score_fields');
        Schema::rename('certification_tool_submissions', 'certification_instrument_submissions');
        Schema::rename('certification_tool_submission_dimension_values', 'certification_instrument_submission_dimension_values');
        Schema::rename('certification_tool_submission_scores', 'certification_instrument_submission_scores');

        Schema::table('certification_instrument_program', function (Blueprint $table) {
            $table->renameColumn('certification_tool_id', 'certification_instrument_id');
        });
        Schema::table('certification_instrument_dimensions', function (Blueprint $table) {
            $table->renameColumn('certification_tool_id', 'certification_instrument_id');
        });
        Schema::table('certification_instrument_dimension_options', function (Blueprint $table) {
            $table->renameColumn('certification_tool_dimension_id', 'certification_instrument_dimension_id');
        });
        Schema::table('certification_instrument_score_fields', function (Blueprint $table) {
            $table->renameColumn('certification_tool_id', 'certification_instrument_id');
        });
        Schema::table('certification_instrument_submissions', function (Blueprint $table) {
            $table->renameColumn('certification_tool_id', 'certification_instrument_id');
        });
        Schema::table('certification_instrument_submission_dimension_values', function (Blueprint $table) {
            $table->renameColumn('certification_tool_submission_id', 'certification_instrument_submission_id');
            $table->renameColumn('certification_tool_dimension_id', 'certification_instrument_dimension_id');
            $table->renameColumn('certification_tool_dimension_option_id', 'certification_instrument_dimension_option_id');
        });
        Schema::table('certification_instrument_submission_scores', function (Blueprint $table) {
            $table->renameColumn('certification_tool_submission_id', 'certification_instrument_submission_id');
            $table->renameColumn('certification_tool_score_field_id', 'certification_instrument_score_field_id');
        });
        Schema::table('certificate_requirements', function (Blueprint $table) {
            $table->renameColumn('certification_tool_id', 'certification_instrument_id');
        });
        Schema::table('certificate_requirement_dimension_rules', function (Blueprint $table) {
            $table->renameColumn('certification_tool_dimension_id', 'certification_instrument_dimension_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificate_requirement_dimension_rules', function (Blueprint $table) {
            $table->renameColumn('certification_instrument_dimension_id', 'certification_tool_dimension_id');
        });
        Schema::table('certificate_requirements', function (Blueprint $table) {
            $table->renameColumn('certification_instrument_id', 'certification_tool_id');
        });
        Schema::table('certification_instrument_submission_scores', function (Blueprint $table) {
            $table->renameColumn('certification_instrument_submission_id', 'certification_tool_submission_id');
            $table->renameColumn('certification_instrument_score_field_id', 'certification_tool_score_field_id');
        });
        Schema::table('certification_instrument_submission_dimension_values', function (Blueprint $table) {
            $table->renameColumn('certification_instrument_submission_id', 'certification_tool_submission_id');
            $table->renameColumn('certification_instrument_dimension_id', 'certification_tool_dimension_id');
            $table->renameColumn('certification_instrument_dimension_option_id', 'certification_tool_dimension_option_id');
        });
        Schema::table('certification_instrument_submissions', function (Blueprint $table) {
            $table->renameColumn('certification_instrument_id', 'certification_tool_id');
        });
        Schema::table('certification_instrument_score_fields', function (Blueprint $table) {
            $table->renameColumn('certification_instrument_id', 'certification_tool_id');
        });
        Schema::table('certification_instrument_dimension_options', function (Blueprint $table) {
            $table->renameColumn('certification_instrument_dimension_id', 'certification_tool_dimension_id');
        });
        Schema::table('certification_instrument_dimensions', function (Blueprint $table) {
            $table->renameColumn('certification_instrument_id', 'certification_tool_id');
        });
        Schema::table('certification_instrument_program', function (Blueprint $table) {
            $table->renameColumn('certification_instrument_id', 'certification_tool_id');
        });

        Schema::rename('certification_instrument_submission_scores', 'certification_tool_submission_scores');
        Schema::rename('certification_instrument_submission_dimension_values', 'certification_tool_submission_dimension_values');
        Schema::rename('certification_instrument_submissions', 'certification_tool_submissions');
        Schema::rename('certification_instrument_score_fields', 'certification_tool_score_fields');
        Schema::rename('certification_instrument_dimension_options', 'certification_tool_dimension_options');
        Schema::rename('certification_instrument_dimensions', 'certification_tool_dimensions');
        Schema::rename('certification_instrument_program', 'certification_tool_program');
        Schema::rename('certification_instruments', 'certification_tools');
    }
};

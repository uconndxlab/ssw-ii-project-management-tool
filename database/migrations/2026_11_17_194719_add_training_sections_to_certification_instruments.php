<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trainings are instruments whose one "sections" dimension allows many options per submission,
 * each with an optional Pass/Fail result. Existing rows stay tools with choose-one dimensions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('certification_instruments', function (Blueprint $table) {
            $table->string('type')->default('tool')->after('name');
        });
        Schema::table('certification_instrument_dimensions', function (Blueprint $table) {
            $table->string('type')->default('choose_one')->after('name');
        });
        Schema::table('certification_instrument_dimension_options', function (Blueprint $table) {
            $table->boolean('scored')->default(false)->after('value');
        });
        Schema::table('certification_instrument_submission_dimension_values', function (Blueprint $table) {
            $table->string('result')->nullable();
            // Added before dropping the old constraint so the submission column stays indexed throughout.
            $table->unique(['certification_instrument_submission_id', 'certification_instrument_dimension_id', 'certification_instrument_dimension_option_id'], 'submission_dimension_option_unique');
        });
        Schema::table('certification_instrument_submission_dimension_values', function (Blueprint $table) {
            $table->dropUnique('submission_dimension_unique');
        });
    }

    public function down(): void
    {
        Schema::table('certification_instrument_submission_dimension_values', function (Blueprint $table) {
            $table->unique(['certification_instrument_submission_id', 'certification_instrument_dimension_id'], 'submission_dimension_unique');
            $table->dropUnique('submission_dimension_option_unique');
            $table->dropColumn('result');
        });
        Schema::table('certification_instrument_dimension_options', function (Blueprint $table) {
            $table->dropColumn('scored');
        });
        Schema::table('certification_instrument_dimensions', function (Blueprint $table) {
            $table->dropColumn('type');
        });
        Schema::table('certification_instruments', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreement_logging_field_assignments', function (Blueprint $table) {
            $table->dropUnique('agreement_field_assignment_unique');
            $table->dropConstrainedForeignId('agreement_logging_field_id');
        });

        Schema::table('contact_family_logging_field_assignments', function (Blueprint $table) {
            $table->dropUnique('family_field_assignment_unique');
            $table->dropConstrainedForeignId('contact_family_logging_field_id');
        });

        Schema::dropIfExists('contact_family_logging_fields');
        Schema::dropIfExists('agreement_logging_fields');
    }

    public function down(): void
    {
        Schema::create('agreement_logging_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('field_type');
            $table->text('help_text')->nullable();
            $table->json('options_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_family_logging_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('field_type');
            $table->text('help_text')->nullable();
            $table->json('options_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->nullable();
            $table->timestamps();
        });

        Schema::table('agreement_logging_field_assignments', function (Blueprint $table) {
            $table->foreignId('agreement_logging_field_id')->nullable()->after('agreement_id')->constrained('agreement_logging_fields')->cascadeOnDelete();

            $table->unique(['agreement_id', 'agreement_logging_field_id'], 'agreement_field_assignment_unique');
        });

        Schema::table('contact_family_logging_field_assignments', function (Blueprint $table) {
            $table->foreignId('contact_family_logging_field_id')->nullable()->after('contact_family_id')->constrained('contact_family_logging_fields')->cascadeOnDelete();

            $table->unique(['contact_family_id', 'contact_family_logging_field_id'], 'family_field_assignment_unique');
        });
    }
};

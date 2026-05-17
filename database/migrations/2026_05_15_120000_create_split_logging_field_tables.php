<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
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

        Schema::create('agreement_logging_field_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agreement_logging_field_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['agreement_id', 'agreement_logging_field_id'], 'agreement_field_assignment_unique');
        });

        Schema::create('contact_family_logging_field_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_family_logging_field_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['contact_family_id', 'contact_family_logging_field_id'], 'family_field_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_family_logging_field_assignments');
        Schema::dropIfExists('agreement_logging_field_assignments');
        Schema::dropIfExists('contact_family_logging_fields');
        Schema::dropIfExists('agreement_logging_fields');
    }
};

<?php

use App\Enums\CertificationToolScoreUnit;
use App\Enums\ProgramScopeMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certification_tools', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('program_scope_mode')->default(ProgramScopeMode::Specific->value);
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certification_tool_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_tool_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['certification_tool_id', 'program_id'], 'cert_tool_program_unique');
        });

        Schema::create('certification_tool_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_tool_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['certification_tool_id', 'slug'], 'cert_tool_dimension_slug_unique');
        });

        Schema::create('certification_tool_dimension_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_tool_dimension_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('value');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['certification_tool_dimension_id', 'value'], 'cert_tool_dimension_option_unique');
        });

        Schema::create('certification_tool_score_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_tool_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('unit')->default(CertificationToolScoreUnit::Percent->value);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['certification_tool_id', 'slug'], 'cert_tool_score_field_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certification_tool_score_fields');
        Schema::dropIfExists('certification_tool_dimension_options');
        Schema::dropIfExists('certification_tool_dimensions');
        Schema::dropIfExists('certification_tool_program');
        Schema::dropIfExists('certification_tools');
    }
};

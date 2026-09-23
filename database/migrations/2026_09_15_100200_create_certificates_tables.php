<?php

use App\Enums\ProgramScopeMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('program_scope_mode')->default(ProgramScopeMode::Specific->value);
            // Null means the certificate never expires.
            $table->unsignedInteger('validity_months')->nullable();
            // Null means contributions are counted all-time rather than on a rolling window.
            $table->unsignedInteger('default_window_months')->nullable();
            $table->string('prerequisite_mode')->default('all');
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('certificate_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['certificate_id', 'program_id']);
        });

        Schema::create('certificate_prerequisites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('required_certificate_id')->constrained('certificates')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['certificate_id', 'required_certificate_id'], 'certificate_prerequisite_unique');
        });

        Schema::create('certification_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug');
            // Null means a standard role available to every certificate.
            $table->foreignId('certificate_id')->nullable()->constrained()->cascadeOnDelete();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['certificate_id', 'slug'], 'certification_role_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certification_roles');
        Schema::dropIfExists('certificate_prerequisites');
        Schema::dropIfExists('certificate_program');
        Schema::dropIfExists('certificates');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_attestations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_requirement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cycle');
            $table->boolean('satisfied')->default(false);
            $table->timestamp('satisfied_at')->nullable();
            $table->foreignId('satisfied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['certificate_enrollment_id', 'certificate_requirement_id', 'cycle'], 'enrollment_attestation_cycle_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_attestations');
    }
};

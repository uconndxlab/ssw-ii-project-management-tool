<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_enrollment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('cycle');
            $table->string('phase');
            $table->timestamp('endorsed_at');
            $table->foreignId('endorsed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('endorsement_note')->nullable();
            $table->json('requirements_snapshot');
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('awarded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('award_note')->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('revoke_reason')->nullable();
            $table->timestamps();
            $table->unique(['certificate_enrollment_id', 'cycle'], 'certificate_award_enrollment_cycle_unique');
        });

        Schema::create('certificate_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certificate_enrollment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_award_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->unsignedInteger('cycle');
            $table->text('reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['certificate_enrollment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_action_logs');
        Schema::dropIfExists('certificate_awards');
    }
};

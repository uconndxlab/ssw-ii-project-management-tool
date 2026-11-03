<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * (candidate, certificate) assignment. Only the fields this release actually writes are
     * included — requirements-met/endorsement/award/renewal columns belong to Docs 3-4 and are
     * deliberately deferred rather than added unused now.
     */
    public function up(): void
    {
        Schema::create('certificate_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('certification_candidate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsoring_agreement_id')->nullable()->constrained('agreements')->nullOnDelete();
            $table->foreignId('supervising_coach_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('not_started');
            $table->string('phase')->default('initial');
            $table->unsignedInteger('cycle')->default(1);
            $table->timestamp('enrolled_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['certification_candidate_id', 'certificate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_enrollments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_certification_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('certification_candidate_id')->constrained()->restrictOnDelete();
            $table->foreignId('agreement_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('certification_role_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('completion_units')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['activity_id', 'certification_candidate_id'], 'activity_cert_candidate_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_certification_candidates');
    }
};

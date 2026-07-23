<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_agreement_funding_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->timestamps();

            $table->unique(
                ['activity_id', 'agreement_id', 'role', 'source_type', 'source_id'],
                'activity_agreement_funding_source_unique'
            );
            $table->index(['activity_id', 'agreement_id', 'role'], 'activity_agreement_funding_role_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_agreement_funding_sources');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverable_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_deliverable_id')->constrained('agreement_deliverables')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['agreement_deliverable_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverable_user');
    }
};

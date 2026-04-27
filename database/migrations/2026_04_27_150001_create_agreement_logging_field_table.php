<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_logging_field', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained()->onDelete('cascade');
            $table->foreignId('logging_field_id')->constrained()->onDelete('cascade');
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['agreement_id', 'logging_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_logging_field');
    }
};

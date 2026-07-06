<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_type_logging_field_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('logging_field_id')->constrained('logging_fields')->cascadeOnDelete();
            $table->boolean('is_required')->default(false);
            $table->timestamps();

            $table->unique(['activity_type_id', 'logging_field_id'], 'activity_type_field_assignment_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_type_logging_field_assignments');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_contact_times', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->decimal('activity_hours', 8, 2);
            $table->decimal('prep_hours', 8, 2)->default(0);
            $table->decimal('follow_up_hours', 8, 2)->default(0);
            $table->timestamps();

            $table->unique('activity_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_contact_times');
    }
};
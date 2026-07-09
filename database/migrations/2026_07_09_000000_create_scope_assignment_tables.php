<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['agreement_id', 'project_id']);
        });

        DB::table('agreements')
            ->whereNotNull('project_id')
            ->orderBy('id')
            ->get(['id', 'project_id'])
            ->each(function ($agreement) {
                DB::table('agreement_project')->updateOrInsert(
                    [
                        'agreement_id' => $agreement->id,
                        'project_id' => $agreement->project_id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            });

        Schema::create('team_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'project_id']);
        });

        Schema::create('team_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'program_id']);
        });

        Schema::create('logging_field_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logging_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['logging_field_id', 'project_id']);
        });

        Schema::create('logging_field_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('logging_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['logging_field_id', 'program_id']);
        });

        Schema::create('contact_family_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contact_family_id', 'project_id']);
        });

        Schema::create('contact_family_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_family_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contact_family_id', 'program_id']);
        });

        Schema::create('activity_type_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['activity_type_id', 'project_id']);
        });

        Schema::create('activity_type_program', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['activity_type_id', 'program_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_type_program');
        Schema::dropIfExists('activity_type_project');
        Schema::dropIfExists('contact_family_program');
        Schema::dropIfExists('contact_family_project');
        Schema::dropIfExists('logging_field_program');
        Schema::dropIfExists('logging_field_project');
        Schema::dropIfExists('team_program');
        Schema::dropIfExists('team_project');
        Schema::dropIfExists('agreement_project');
    }
};
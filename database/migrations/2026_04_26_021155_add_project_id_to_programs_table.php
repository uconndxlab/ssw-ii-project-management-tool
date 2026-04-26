<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create a default project for existing programs
        $defaultProjectId = DB::table('projects')->insertGetId([
            'name' => 'Default Project',
            'description' => 'Auto-created to organize existing programs',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Add the column as nullable first
        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('id');
        });

        // Update all existing programs to use the default project
        DB::table('programs')->update(['project_id' => $defaultProjectId]);

        // Now make it non-nullable and add the foreign key
        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });

        // Optionally remove the default project
        DB::table('projects')->where('name', 'Default Project')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_project', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['program_id', 'project_id']);
        });

        $now = now();

        DB::insert(
            'INSERT INTO program_project (program_id, project_id, created_at, updated_at)
             SELECT id, project_id, ?, ? FROM programs WHERE project_id IS NOT NULL',
            [$now, $now]
        );

        Schema::table('programs', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });
    }

    public function down(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('id');
        });

        $rows = DB::table('program_project')
            ->select('program_id', DB::raw('MIN(project_id) as project_id'))
            ->groupBy('program_id')
            ->get();

        foreach ($rows as $row) {
            DB::table('programs')
                ->where('id', $row->program_id)
                ->update(['project_id' => $row->project_id]);
        }

        DB::table('programs')->whereNull('project_id')->delete();

        Schema::table('programs', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable(false)->change();
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });

        Schema::dropIfExists('program_project');
    }
};

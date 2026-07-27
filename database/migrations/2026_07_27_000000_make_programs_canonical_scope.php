<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('agreement_project');
        Schema::dropIfExists('activity_project');
        Schema::dropIfExists('organization_project');
        Schema::dropIfExists('team_project');
        Schema::dropIfExists('user_project');
        Schema::dropIfExists('logging_field_project');
        Schema::dropIfExists('contact_family_project');
        Schema::dropIfExists('activity_type_project');

        Schema::table('agreements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });
    }

    /**
     * Rollback recreates the current inferred project assignments. It cannot
     * restore historical explicit project selections removed by this migration.
     */
    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->foreignId('project_id')
                ->nullable()
                ->after('name')
                ->constrained('projects')
                ->nullOnDelete();
        });

        $this->createProjectPivot('agreement_project', 'agreement_id', 'agreements');
        $this->createActivityProjectPivot();
        $this->createProjectPivot('organization_project', 'organization_id', 'organizations');
        $this->createProjectPivot('team_project', 'team_id', 'teams');
        $this->createProjectPivot('user_project', 'user_id', 'users');
        $this->createProjectPivot('logging_field_project', 'logging_field_id', 'logging_fields');
        $this->createProjectPivot('contact_family_project', 'contact_family_id', 'contact_families');
        $this->createProjectPivot('activity_type_project', 'activity_type_id', 'activity_types');

        $this->backfillProjects('agreement_program', 'agreement_project', 'agreement_id');
        $this->backfillProjects('activity_program', 'activity_project', 'activity_id');
        $this->backfillProjects('organization_program', 'organization_project', 'organization_id');
        $this->backfillProjects('team_program', 'team_project', 'team_id');
        $this->backfillProjects('user_program', 'user_project', 'user_id');
        $this->backfillProjects('logging_field_program', 'logging_field_project', 'logging_field_id');
        $this->backfillProjects('contact_family_program', 'contact_family_project', 'contact_family_id');
        $this->backfillProjects('activity_type_program', 'activity_type_project', 'activity_type_id');

        DB::table('agreements')->update([
            'project_id' => DB::raw(
                '(SELECT MIN(agreement_project.project_id)
                  FROM agreement_project
                  WHERE agreement_project.agreement_id = agreements.id)'
            ),
        ]);
    }

    private function backfillProjects(string $programPivot, string $projectPivot, string $foreignKey): void
    {
        DB::table($projectPivot)->insertUsing(
            [$foreignKey, 'project_id', 'created_at', 'updated_at'],
            DB::table($programPivot)
                ->join('program_project', 'program_project.program_id', '=', $programPivot.'.program_id')
                ->select([
                    $programPivot.'.'.$foreignKey,
                    'program_project.project_id',
                    DB::raw('CURRENT_TIMESTAMP'),
                    DB::raw('CURRENT_TIMESTAMP'),
                ])
                ->distinct()
        );
    }

    private function createProjectPivot(string $tableName, string $foreignKey, string $parentTable): void
    {
        Schema::create($tableName, function (Blueprint $table) use ($foreignKey, $parentTable) {
            $table->id();
            $table->foreignId($foreignKey)->constrained($parentTable)->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique([$foreignKey, 'project_id']);
        });
    }

    private function createActivityProjectPivot(): void
    {
        Schema::create('activity_project', function (Blueprint $table) {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['activity_id', 'project_id']);
        });
    }
};

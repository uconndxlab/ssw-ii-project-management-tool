<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop old foreign key columns from agreements table.
        // Constraint names may still be from the original `projects` table
        // (Postgres does not rename FKs on Schema::rename).
        Schema::table('agreements', function (Blueprint $table) {
            $this->dropForeignsOnColumns($table, 'agreements', ['organization_id', 'state_id']);
            $table->dropColumn(['organization_id', 'state_id']);
        });

        // Drop old foreign key column from activities table.
        // Constraint name may still be `engagements_project_id_foreign`.
        Schema::table('activities', function (Blueprint $table) {
            $this->dropForeignsOnColumns($table, 'activities', ['agreement_id']);
            $table->dropColumn('agreement_id');
        });

        // Create agreement_organization pivot table
        Schema::create('agreement_organization', function (Blueprint $table) {
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['agreement_id', 'organization_id']);
        });

        // Create agreement_state pivot table
        Schema::create('agreement_state', function (Blueprint $table) {
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['agreement_id', 'state_id']);
        });

        // Create activity_agreement pivot table
        Schema::create('activity_agreement', function (Blueprint $table) {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('agreement_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['activity_id', 'agreement_id']);
        });

        // Create activity_organization pivot table
        Schema::create('activity_organization', function (Blueprint $table) {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['activity_id', 'organization_id']);
        });

        // Create activity_state pivot table
        Schema::create('activity_state', function (Blueprint $table) {
            $table->foreignId('activity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('state_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            
            $table->unique(['activity_id', 'state_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop pivot tables
        Schema::dropIfExists('activity_state');
        Schema::dropIfExists('activity_organization');
        Schema::dropIfExists('activity_agreement');
        Schema::dropIfExists('agreement_state');
        Schema::dropIfExists('agreement_organization');

        // Restore foreign key columns to agreements table
        Schema::table('agreements', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->foreignId('state_id')->nullable()->after('organization_id')->constrained()->cascadeOnDelete();
        });

        // Restore foreign key column to activities table
        Schema::table('activities', function (Blueprint $table) {
            $table->foreignId('agreement_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });
    }

    /**
     * Drop foreign keys by inspecting the live constraint names, which can lag
     * behind table/column renames on Postgres.
     *
     * @param  list<string>  $columns
     */
    private function dropForeignsOnColumns(Blueprint $table, string $tableName, array $columns): void
    {
        foreach (Schema::getForeignKeys($tableName) as $foreignKey) {
            if (array_intersect($foreignKey['columns'], $columns) === []) {
                continue;
            }

            $table->dropForeign($foreignKey['name']);
        }
    }
};

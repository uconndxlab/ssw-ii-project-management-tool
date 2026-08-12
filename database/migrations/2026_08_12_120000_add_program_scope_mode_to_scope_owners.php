<?php

use App\Enums\ProgramScopeMode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('program_scope_mode')->default(ProgramScopeMode::Specific->value)->after('supervisor_id');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->string('program_scope_mode')->default(ProgramScopeMode::Specific->value)->after('active');
        });

        Schema::table('agreements', function (Blueprint $table) {
            $table->string('program_scope_mode')->default(ProgramScopeMode::Specific->value)->after('require_payee');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->string('program_scope_mode')->default(ProgramScopeMode::Specific->value)->after('po_number');
        });

        Schema::table('logging_fields', function (Blueprint $table) {
            $table->string('program_scope_mode')->default(ProgramScopeMode::All->value)->after('available_in_activities');
        });

        Schema::table('contact_families', function (Blueprint $table) {
            $table->string('program_scope_mode')->default(ProgramScopeMode::All->value)->after('sort_order');
        });

        Schema::table('activity_types', function (Blueprint $table) {
            $table->string('program_scope_mode')->default(ProgramScopeMode::All->value)->after('duration_hours');
        });

        DB::table('users')->update([
            'program_scope_mode' => ProgramScopeMode::None->value,
        ]);

        DB::table('users')
            ->whereIn('id', DB::table('user_program')->select('user_id'))
            ->update(['program_scope_mode' => ProgramScopeMode::Specific->value]);

        DB::table('teams')->update([
            'program_scope_mode' => ProgramScopeMode::None->value,
        ]);

        DB::table('teams')
            ->whereIn('id', DB::table('team_program')->select('team_id'))
            ->update(['program_scope_mode' => ProgramScopeMode::Specific->value]);

        DB::table('agreements')->update([
            'program_scope_mode' => ProgramScopeMode::None->value,
        ]);

        DB::table('agreements')
            ->whereIn('id', DB::table('agreement_program')->select('agreement_id'))
            ->update(['program_scope_mode' => ProgramScopeMode::Specific->value]);

        DB::table('organizations')->update([
            'program_scope_mode' => ProgramScopeMode::None->value,
        ]);

        DB::table('organizations')
            ->whereIn('id', DB::table('organization_program')->select('organization_id'))
            ->update(['program_scope_mode' => ProgramScopeMode::Specific->value]);

        DB::table('logging_fields')->update([
            'program_scope_mode' => ProgramScopeMode::All->value,
        ]);

        DB::table('logging_fields')
            ->whereIn('id', DB::table('logging_field_program')->select('logging_field_id'))
            ->update(['program_scope_mode' => ProgramScopeMode::Specific->value]);

        DB::table('contact_families')->update([
            'program_scope_mode' => ProgramScopeMode::All->value,
        ]);

        DB::table('contact_families')
            ->whereIn('id', DB::table('contact_family_program')->select('contact_family_id'))
            ->update(['program_scope_mode' => ProgramScopeMode::Specific->value]);

        DB::table('activity_types')->update([
            'program_scope_mode' => ProgramScopeMode::All->value,
        ]);

        DB::table('activity_types')
            ->whereIn('id', DB::table('activity_type_program')->select('activity_type_id'))
            ->update(['program_scope_mode' => ProgramScopeMode::Specific->value]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('activity_types', function (Blueprint $table) {
            $table->dropColumn('program_scope_mode');
        });

        Schema::table('contact_families', function (Blueprint $table) {
            $table->dropColumn('program_scope_mode');
        });

        Schema::table('logging_fields', function (Blueprint $table) {
            $table->dropColumn('program_scope_mode');
        });

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn('program_scope_mode');
        });

        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('program_scope_mode');
        });

        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn('program_scope_mode');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('program_scope_mode');
        });
    }
};
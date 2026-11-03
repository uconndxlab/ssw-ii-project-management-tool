<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Converts the agreement-local name-only candidate rows into roster links against the new
     * global `certification_candidates` table. Backfills exactly one new person per legacy row
     * (no name auto-merge), preserving the row's id and agreement/program/notes association.
     * The candidate id column is staged nullable for the backfill, then enforced not-null.
     */
    public function up(): void
    {
        Schema::table('agreement_certification_candidates', function (Blueprint $table) {
            $table->foreignId('certification_candidate_id')
                ->nullable()
                ->after('agreement_id')
                ->constrained('certification_candidates')
                ->restrictOnDelete();
            $table->unsignedInteger('sort_order')->default(0)->after('certification_candidate_id');
        });

        $rows = DB::table('agreement_certification_candidates')
            ->select('id', 'agreement_id', 'name')
            ->orderBy('agreement_id')
            ->orderBy('id')
            ->get();

        $sortOrderByAgreement = [];

        foreach ($rows as $row) {
            $candidateId = DB::table('certification_candidates')->insertGetId([
                'name' => $row->name,
                'email' => null,
                'phone' => null,
                'start_date' => null,
                'notes' => null,
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sortOrderByAgreement[$row->agreement_id] = ($sortOrderByAgreement[$row->agreement_id] ?? -1) + 1;

            DB::table('agreement_certification_candidates')
                ->where('id', $row->id)
                ->update([
                    'certification_candidate_id' => $candidateId,
                    'sort_order' => $sortOrderByAgreement[$row->agreement_id],
                ]);
        }

        Schema::table('agreement_certification_candidates', function (Blueprint $table) {
            $table->foreignId('certification_candidate_id')->nullable(false)->change();
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        Schema::table('agreement_certification_candidates', function (Blueprint $table) {
            $table->string('name')->nullable()->after('program_id');
        });

        DB::table('agreement_certification_candidates')
            ->join('certification_candidates', 'certification_candidates.id', '=', 'agreement_certification_candidates.certification_candidate_id')
            ->update(['agreement_certification_candidates.name' => DB::raw('certification_candidates.name')]);

        Schema::table('agreement_certification_candidates', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->dropConstrainedForeignId('certification_candidate_id');
            $table->dropColumn('sort_order');
        });
    }
};

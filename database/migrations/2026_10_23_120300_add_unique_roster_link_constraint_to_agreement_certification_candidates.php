<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One roster link per agreement/candidate: different legacy rows with equal names remain
     * separate candidates (no auto-merge), but the same candidate cannot be linked to one
     * agreement's roster twice.
     */
    public function up(): void
    {
        Schema::table('agreement_certification_candidates', function (Blueprint $table) {
            $table->unique(['agreement_id', 'certification_candidate_id'], 'agreement_certification_candidates_agreement_candidate_unique');
        });
    }

    public function down(): void
    {
        Schema::table('agreement_certification_candidates', function (Blueprint $table) {
            $table->dropUnique('agreement_certification_candidates_agreement_candidate_unique');
        });
    }
};

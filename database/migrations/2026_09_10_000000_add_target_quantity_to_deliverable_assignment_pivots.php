<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('deliverable_user', 'target_quantity')) {
            Schema::table('deliverable_user', function (Blueprint $table) {
                $table->decimal('target_quantity', 10, 2)->nullable()->after('source_team_id');
            });
        }

        if (! Schema::hasColumn('deliverable_team', 'target_quantity')) {
            Schema::table('deliverable_team', function (Blueprint $table) {
                $table->decimal('target_quantity', 10, 2)->nullable()->after('team_id');
            });
        }

        $this->backfillIndividualDeliverableTargets();
    }

    public function down(): void
    {
        if (Schema::hasColumn('deliverable_user', 'target_quantity')) {
            Schema::table('deliverable_user', function (Blueprint $table) {
                $table->dropColumn('target_quantity');
            });
        }

        if (Schema::hasColumn('deliverable_team', 'target_quantity')) {
            Schema::table('deliverable_team', function (Blueprint $table) {
                $table->dropColumn('target_quantity');
            });
        }
    }

    private function backfillIndividualDeliverableTargets(): void
    {
        $deliverables = DB::table('agreement_deliverables')
            ->where('contribution_basis', 'user')
            ->where('user_grouping_mode', 'individual')
            ->whereNotNull('target_quantity')
            ->where('target_quantity', '>', 0)
            ->get(['id', 'target_quantity']);

        foreach ($deliverables as $deliverable) {
            $eachTarget = (float) $deliverable->target_quantity;

            $pivotRows = DB::table('deliverable_user')
                ->where('agreement_deliverable_id', $deliverable->id)
                ->get(['id', 'unassigned_at']);

            if ($pivotRows->isEmpty()) {
                continue;
            }

            foreach ($pivotRows as $pivotRow) {
                DB::table('deliverable_user')
                    ->where('id', $pivotRow->id)
                    ->update(['target_quantity' => $eachTarget]);
            }

            $activeCount = $pivotRows->filter(fn ($row) => $row->unassigned_at === null)->count();

            if ($activeCount > 0) {
                $newTotal = round($eachTarget * $activeCount, 2);

                DB::table('agreement_deliverables')
                    ->where('id', $deliverable->id)
                    ->update(['target_quantity' => $newTotal]);
            }
        }
    }
};

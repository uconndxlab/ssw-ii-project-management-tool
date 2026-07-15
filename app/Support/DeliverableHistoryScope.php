<?php

namespace App\Support;

use App\Models\AgreementActivityHistory;
use App\Models\AgreementDeliverable;
use Illuminate\Support\Collection;

class DeliverableHistoryScope
{
    /**
     * @param \Illuminate\Support\Collection<int, AgreementActivityHistory> $histories
     */
    public static function hasMatchingHistory(Collection $histories, AgreementDeliverable $deliverable): bool
    {
        return $histories->contains(function (AgreementActivityHistory $history) use ($deliverable) {
            if ((int) $history->contact_family_id !== (int) $deliverable->contact_family_id) {
                return false;
            }

            if ($deliverable->activity_type_id && (int) $history->activity_type_id !== (int) $deliverable->activity_type_id) {
                return false;
            }

            if ($deliverable->program_id) {
                return collect($history->program_ids_snapshot ?? [])
                    ->map(fn ($id) => (int) $id)
                    ->contains((int) $deliverable->program_id);
            }

            return true;
        });
    }
}

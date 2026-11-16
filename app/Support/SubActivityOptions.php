<?php

namespace App\Support;

use App\Models\ActivityType;
use App\Models\AgreementDeliverable;
use App\Models\SubActivity;
use Illuminate\Support\Collection;

class SubActivityOptions
{
    /**
     * @param  array<int, int>  $agreementIds
     * @return Collection<int, SubActivity>
     */
    public static function availableFor(int $activityTypeId, array $agreementIds = [], ?int $keepSubActivityId = null): Collection
    {
        $type = ActivityType::query()->find($activityTypeId);
        if (! $type || ! $type->active) {
            return collect();
        }

        $query = SubActivity::query()->with('activityType')->where('activity_type_id', $type->id);
        $query->where(function ($query) use ($type, $agreementIds, $keepSubActivityId) {
            $query->where(function ($query) use ($type, $agreementIds) {
                $query->active();
                if ($agreementIds !== []) {
                    $deliverables = AgreementDeliverable::query()->whereIn('agreement_id', $agreementIds)
                        ->whereNull('retired_at')
                        ->where(function ($query) use ($type) {
                            $query->where('activity_type_id', $type->id)
                                ->orWhere(fn ($query) => $query->where('contact_family_id', $type->contact_family_id)->whereNull('activity_type_id'));
                        })->get();
                    $hasBroadDeliverable = $deliverables->contains(fn ($deliverable) => ! $deliverable->activity_type_id || ! $deliverable->sub_activity_id);
                    if (! $hasBroadDeliverable) {
                        $query->whereIn('id', $deliverables->pluck('sub_activity_id')->filter());
                    }
                }
            });
            if ($keepSubActivityId !== null) {
                $query->orWhereKey($keepSubActivityId);
            }
        });

        return $query->orderBy('sort_order')->orderBy('name')->get();
    }
}

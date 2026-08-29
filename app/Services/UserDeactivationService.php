<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class UserDeactivationService
{
    public function revokeMembership(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user->teams()->detach();
            $user->agreements()->detach();
            $user->principalInvestigatorAgreements()->detach();
            $user->organizations()->detach();
            $user->programs()->sync([]);
            $user->privileges()->delete();
            $user->forceFill(['is_supervisor' => false])->save();

            User::query()
                ->where('supervisor_id', $user->id)
                ->update(['supervisor_id' => null]);

            $user->load(['deliverables']);

            foreach ($user->deliverables as $deliverable) {
                if ($deliverable->pivot->unassigned_at) {
                    continue;
                }

                $user->deliverables()->updateExistingPivot($deliverable->id, [
                    'unassigned_at' => now(),
                ]);
            }

            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }
}

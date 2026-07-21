<?php

namespace App\Support;

use App\Models\Agreement;
use App\Models\User;
use Illuminate\Support\Collection;

class UserDeliverableReporting
{
    /**
     * @return Collection<int, array{agreement: Agreement, direct: bool, teams: Collection, deliverableGroups: Collection}>
     */
    public static function buildAgreementReports(User $user): Collection
    {
        $scope = $user->getScopeBySource();
        $accessMeta = self::agreementAccessMeta($scope);

        if ($accessMeta->isEmpty()) {
            return collect();
        }

        $agreements = Agreement::query()
            ->active()
            ->whereIn('id', $accessMeta->keys())
            ->with([
                'organizations',
                'states',
                'teams.users',
                'users',
                'deliverables.contactFamily',
                'deliverables.activityType',
                'deliverables.program',
                'deliverables.users',
                'deliverables.teams',
                'deliverables.contributions.contributor',
                'deliverables.contributions.activityHistory',
            ])
            ->orderBy('name')
            ->get();

        return $agreements->map(function (Agreement $agreement) use ($user, $accessMeta) {
            $meta = $accessMeta->get((int) $agreement->id);

            return [
                'agreement' => $agreement,
                'direct' => (bool) $meta['direct'],
                'teams' => $meta['teams'],
                'deliverableGroups' => AgreementDeliverableDisplay::buildGroupedProgressForUser($agreement, $user),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return Collection<int, array{direct: bool, teams: Collection}>
     */
    private static function agreementAccessMeta(array $scope): Collection
    {
        $meta = collect();

        foreach ($scope['direct']['agreements'] as $agreement) {
            $meta->put((int) $agreement->id, [
                'direct' => true,
                'teams' => collect(),
            ]);
        }

        foreach ($scope['viaTeams']['agreements'] as $row) {
            $agreementId = (int) $row['agreement']->id;
            if ($meta->has($agreementId)) {
                continue;
            }

            $meta->put($agreementId, [
                'direct' => false,
                'teams' => $row['teams'],
            ]);
        }

        return $meta;
    }
}

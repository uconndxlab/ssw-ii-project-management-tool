<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\Team;
use App\Models\User;
use App\Support\UserDeliverableReporting;
use Illuminate\Support\Collection;

class UserShowPageData
{
    /**
     * @return array{
     *     user: User,
     *     recentActivities: Collection<int, Activity>|\Illuminate\Database\Eloquent\Collection<int, Activity>|mixed,
     *     scopeBySource: array<string, mixed>,
     *     agreementReports: Collection<int, array{agreement: Agreement, direct: bool, teams: Collection<int, Team>, deliverableGroups: Collection<int, array<string, mixed>>}>
     * }
     */
    public static function for(User $user): array
    {
        $user->load([
            'supervisor',
            'programs.projects',
            'agreements.organizations',
            'agreements.states',
            'teams.programs.projects',
            'teams.agreements.organizations',
            'teams.agreements.states',
        ]);

        $scopeBySource = $user->getScopeBySource();
        $agreementReports = UserDeliverableReporting::buildAgreementReports($user);

        $recentActivities = $user->activities()
            ->with(['activityType.contactFamily', 'user', 'agreements'])
            ->orderByRecentDisplay()
            ->take(10)
            ->get();

        return compact('user', 'recentActivities', 'scopeBySource', 'agreementReports');
    }
}

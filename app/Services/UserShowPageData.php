<?php

namespace App\Services;

use App\Models\User;
use App\Support\UserDeliverableReporting;
use Illuminate\Support\Collection;

class UserShowPageData
{
    /**
     * @return array{user: User, recentActivities: Collection, scopeBySource: array, agreementReports: Collection}
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

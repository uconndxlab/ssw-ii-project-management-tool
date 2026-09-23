<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\Organization;
use App\Models\State;

class DashboardController extends Controller
{
    public function index()
    {
        $user = $this->actor();

        if ($user->isSystemAdmin()) {
            return $this->adminHome();
        }

        return $this->userHome($user);
    }

    protected function adminHome()
    {
        $user = $this->actor();

        // YTD activities
        $ytdActivities = Activity::whereYear('engagement_date', now()->year)
            ->with(['activityType.contactFamily', 'user', 'agreements'])
            ->get();

        // YTD totals
        $ytdTotals = [
            'activities' => $ytdActivities->count(),
            'hours' => $ytdActivities->sum(fn ($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
            'participants' => $ytdActivities->sum('participant_count'),
        ];

        // This month activities
        $thisMonthActivities = Activity::whereYear('engagement_date', now()->year)
            ->whereMonth('engagement_date', now()->month)
            ->count();

        // Global stats
        $stats = [
            'active_agreements' => Agreement::active()->count(),
            'activities_this_month' => $thisMonthActivities,
            'organizations' => Organization::count(),
            'states' => State::count(),
        ];

        // Recent 10 activities (system-wide)
        $recentActivities = Activity::with(['activityType.contactFamily', 'user', 'agreements'])
            ->orderByRecentDisplay()
            ->limit(10)
            ->get();

        // Get all agreements with stats (boolean active only)
        $agreements = Agreement::active()
            ->with(['organizations', 'states'])
            ->withCount('activities')
            ->withMax('activities', 'engagement_date')
            ->orderBy('name')
            ->get();

        // Admins get their own personal work shown alongside the system-wide stats
        ['agreements' => $myAgreements, 'activities' => $myActivities, 'deliverables' => $myAssignedDeliverables] = $this->personalWorkData($user);

        return view('home', compact('ytdTotals', 'recentActivities', 'agreements', 'stats', 'user', 'myActivities', 'myAgreements', 'myAssignedDeliverables'));
    }

    protected function userHome($user)
    {
        ['agreements' => $myAgreements, 'activities' => $myActivities, 'deliverables' => $myAssignedDeliverables] = $this->personalWorkData($user);

        // My YTD hours (activities I personally logged)
        $myYtdActivities = Activity::where('user_id', $user->id)
            ->whereYear('engagement_date', now()->year)
            ->get();

        $myYtdHours = $myYtdActivities->sum(fn ($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0));

        // This month for user
        $myThisMonthActivities = Activity::where('user_id', $user->id)
            ->whereYear('engagement_date', now()->year)
            ->whereMonth('engagement_date', now()->month)
            ->count();

        // Global stats
        $stats = [
            'active_agreements' => $myAgreements->count(),
            'my_activities_ytd' => $myYtdActivities->count(),
            'my_activities_this_month' => $myThisMonthActivities,
            'my_total_hours_ytd' => $myYtdHours,
        ];

        $recentActivities = collect();

        return view('home', compact('myAgreements', 'myActivities', 'stats', 'user', 'myActivities', 'myAgreements', 'myAssignedDeliverables', 'recentActivities'));
    }

    /**
     * Agreements, activities, and deliverables personally assigned to the given user,
     * used for the "My Work" dashboard panel for both regular users and admins.
     */
    protected function personalWorkData($user)
    {
        $myAgreements = $user->accessibleAgreementsQuery()
            ->where('agreements.active', true)
            ->with(['organizations', 'states'])
            ->withCount('activities')
            ->withMax('activities', 'engagement_date')
            ->get();

        $agreementIds = $user->accessibleAgreementsQuery()->pluck('agreements.id');

        $myActivities = Activity::whereHas('agreements', function ($query) use ($agreementIds) {
            $query->whereIn('agreements.id', $agreementIds);
        })
            ->with(['activityType.contactFamily', 'user', 'agreements', 'participants'])
            ->orderByRecentDisplay()
            ->limit(10)
            ->get();

        $myAssignedDeliverables = $user->deliverables()
            ->wherePivotNull('unassigned_at')
            ->whereNull('agreement_deliverables.retired_at')
            ->whereHas('agreement', fn ($query) => $query->where('active', true))
            ->with(['agreement.organizations', 'activityType', 'contactFamily'])
            ->get();

        return [
            'agreements' => $myAgreements,
            'activities' => $myActivities,
            'deliverables' => $myAssignedDeliverables,
        ];
    }
}

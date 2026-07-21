<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Models\Organization;
use App\Models\State;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if ($user->isAdmin()) {
            return $this->adminHome();
        }
        
        return $this->userHome($user);
    }
    
    protected function adminHome()
    {
        $user = Auth::user();
        
        // YTD activities
        $ytdActivities = Activity::whereYear('engagement_date', now()->year)
            ->with(['activityType.contactFamily', 'user', 'agreements'])
            ->get();

        // YTD totals
        $ytdTotals = [
            'activities' => $ytdActivities->count(),
            'hours' => $ytdActivities->sum(fn($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
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
            ->orderByDesc('engagement_date')
            ->limit(10)
            ->get();

        // Get all agreements with stats (boolean active only)
        $agreements = Agreement::active()
            ->with(['organizations', 'states'])
            ->withCount('activities')
            ->withMax('activities', 'engagement_date')
            ->orderBy('name')
            ->get();

        // Always define these for the view
        $myActivities = collect();
        $myAgreements = collect();
        $myAssignedDeliverables = collect();

        return view('home', compact('ytdTotals', 'recentActivities', 'agreements', 'stats', 'user', 'myActivities', 'myAgreements', 'myAssignedDeliverables'));
    }
    
    protected function userHome($user)
    {
        // Get user's agreements
        $myAgreements = $user->accessibleAgreementsQuery()
            ->where('agreements.active', true)
            ->with(['organizations', 'states'])
            ->withCount('activities')
            ->withMax('activities', 'engagement_date')
            ->get();

        $allAssignedAgreementIds = $user->accessibleAgreementsQuery()->pluck('agreements.id');

        // Get activities for user's agreements (include inactive agreements for history)
        $agreementIds = $allAssignedAgreementIds;

        // My recent activities (last 10)
        $myActivities = Activity::whereHas('agreements', function ($query) use ($agreementIds) {
                $query->whereIn('agreements.id', $agreementIds);
            })
            ->with(['activityType.contactFamily', 'user', 'agreements', 'participants'])
            ->orderByDesc('engagement_date')
            ->limit(10)
            ->get();
        
        // My YTD hours (activities I personally logged)
        $myYtdActivities = Activity::where('user_id', $user->id)
            ->whereYear('engagement_date', now()->year)
            ->get();
        
        $myYtdHours = $myYtdActivities->sum(fn($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0));
        
        // This month for user
        $myThisMonthActivities = Activity::where('user_id', $user->id)
            ->whereYear('engagement_date', now()->year)
            ->whereMonth('engagement_date', now()->month)
            ->count();

        // Deliverables assigned to this user
        $myAssignedDeliverables = $user->deliverables()
            ->wherePivotNull('unassigned_at')
            ->whereHas('agreement', fn ($query) => $query->where('active', true))
            ->with(['agreement.organizations', 'activityType', 'contactFamily'])
            ->get();

        // Global stats
        $stats = [
            'active_agreements'       => $myAgreements->count(),
            'my_activities_ytd'       => $myYtdActivities->count(),
            'my_activities_this_month'=> $myThisMonthActivities,
            'my_total_hours_ytd'      => $myYtdHours,
        ];

        $recentActivities = collect();

        return view('home', compact('myAgreements', 'myActivities', 'stats', 'user', 'myActivities', 'myAgreements', 'myAssignedDeliverables', 'recentActivities'));
    }
}

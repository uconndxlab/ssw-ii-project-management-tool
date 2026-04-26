<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Agreement;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        
        if ($user->isAdmin()) {
            return $this->adminDashboard();
        }
        
        return $this->userDashboard($user);
    }
    
    protected function adminDashboard()
    {
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

        // Recent 10 activities
        $recentActivities = Activity::with(['activityType.contactFamily', 'user', 'agreements'])
            ->orderByDesc('engagement_date')
            ->limit(10)
            ->get();

        // Agreements list for dashboard
        $user = Auth::user();

        $agreements = $user->agreements()
            ->with(['organizations', 'states'])
            ->withCount('activities')
            ->withMax('activities', 'engagement_date')
            ->orderBy('name')
            ->get();

        return view('dashboard', compact('ytdTotals', 'recentActivities', 'agreements'));
    }
    
    protected function userDashboard($user)
    {
        // Get user's agreements
        $myAgreements = $user->agreements()
            ->with(['organizations', 'states'])
            ->withCount('activities')
            ->withMax('activities', 'engagement_date')
            ->get();
        
        // Get activities for user's agreements
        $agreementIds = $myAgreements->pluck('id');
        
        // My recent activities (last 10)
        $myActivities = Activity::whereHas('agreements', function($query) use ($agreementIds) {
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
        
        return view('dashboard-user', compact('myAgreements', 'myActivities', 'myYtdHours'));
    }
}

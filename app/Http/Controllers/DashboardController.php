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
            ->with(['activityType.contactFamily', 'user', 'agreement'])
            ->get();
        
        // YTD totals
        $ytdTotals = [
            'activities' => $ytdActivities->count(),
            'hours' => $ytdActivities->sum(fn($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
            'participants' => $ytdActivities->sum('participant_count'),
        ];
        
        // Recent 10 activities
        $recentActivities = Activity::with(['activityType.contactFamily', 'user', 'agreement'])
            ->orderByDesc('engagement_date')
            ->limit(10)
            ->get();
        
        return view('dashboard', compact('ytdTotals', 'recentActivities'));
    }
    
    protected function userDashboard($user)
    {
        // Get user's agreements
        $myAgreements = $user->agreements()->with(['organization', 'state'])->get();
        
        // Get activities for user's agreements
        $agreementIds = $myAgreements->pluck('id');
        
        // My recent activities (last 10)
        $myActivities = Activity::whereIn('agreement_id', $agreementIds)
            ->with(['activityType.contactFamily', 'user', 'agreement'])
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

<?php

namespace App\Http\Controllers;

use App\Models\Engagement;
use App\Models\Project;
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
        // YTD engagements
        $ytdEngagements = Engagement::whereYear('engagement_date', now()->year)
            ->with(['activityType.contactFamily', 'user', 'project'])
            ->get();
        
        // YTD totals
        $ytdTotals = [
            'engagements' => $ytdEngagements->count(),
            'hours' => $ytdEngagements->sum(fn($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
            'participants' => $ytdEngagements->sum('participant_count'),
        ];
        
        // Recent 10 engagements
        $recentEngagements = Engagement::with(['activityType.contactFamily', 'user', 'project'])
            ->orderByDesc('engagement_date')
            ->limit(10)
            ->get();
        
        return view('dashboard', compact('ytdTotals', 'recentEngagements'));
    }
    
    protected function userDashboard($user)
    {
        // Get user's projects
        $myProjects = $user->projects()->with(['organization', 'state'])->get();
        
        // Get engagements for user's projects
        $projectIds = $myProjects->pluck('id');
        
        // My recent engagements (last 10)
        $myEngagements = Engagement::whereIn('project_id', $projectIds)
            ->with(['activityType.contactFamily', 'user', 'project'])
            ->orderByDesc('engagement_date')
            ->limit(10)
            ->get();
        
        // My YTD hours (engagements I personally logged)
        $myYtdEngagements = Engagement::where('user_id', $user->id)
            ->whereYear('engagement_date', now()->year)
            ->get();
        
        $myYtdHours = $myYtdEngagements->sum(fn($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0));
        
        return view('dashboard-user', compact('myProjects', 'myEngagements', 'myYtdHours'));
    }
}

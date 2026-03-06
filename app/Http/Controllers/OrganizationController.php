<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\State;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::with(['state', 'agreements'])->orderBy('name')->paginate(20);
        
        return view('organizations.index', compact('organizations'));
    }

    public function show(Organization $organization)
    {
        // Load agreements with relationships
        $agreements = $organization->agreements()->with(['state', 'users'])->get();
        
        // Get all activities for this organization's agreements
        $allActivities = \App\Models\Activity::whereIn('agreement_id', $agreements->pluck('id'))
            ->with(['activityType.contactFamily', 'user', 'agreement'])
            ->orderByDesc('engagement_date')
            ->get();
        
        // Recent activities (last 5)
        $recentActivities = $allActivities->take(5);
        
        // Unique team members across all agreements
        $teamMembers = $agreements->pluck('users')->flatten()->unique('id')->sortBy('name');
        
        // YTD activities
        $ytdActivities = $allActivities->filter(fn($e) => $e->engagement_date->year === now()->year);
        
        // YTD totals
        $ytdTotals = [
            'activities' => $ytdActivities->count(),
            'hours' => $ytdActivities->sum(fn($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
            'participants' => $ytdActivities->sum('participant_count'),
        ];
        
        // Breakdown by contact family
        $contactFamilyBreakdown = $ytdActivities->groupBy(fn($e) => $e->activityType->contactFamily->name)
            ->map(fn($group) => $group->count())
            ->sortDesc();
        
        return view('organizations.show', compact(
            'organization',
            'agreements',
            'recentActivities',
            'teamMembers',
            'ytdTotals',
            'contactFamilyBreakdown'
        ));
    }

    public function create()
    {
        $states = State::orderBy('name')->get();
        
        return view('organizations.create', compact('states'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'state_id' => ['required', 'exists:states,id'],
        ]);

        Organization::create($validated);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function edit(Organization $organization)
    {
        $states = State::orderBy('name')->get();
        
        return view('organizations.edit', compact('organization', 'states'));
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'state_id' => ['required', 'exists:states,id'],
        ]);

        $organization->update($validated);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization)
    {
        $organization->delete();

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\State;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        $organizations = Organization::with(['state', 'projects'])->orderBy('name')->paginate(20);
        
        return view('organizations.index', compact('organizations'));
    }

    public function show(Organization $organization)
    {
        // Load projects with relationships
        $projects = $organization->projects()->with(['state', 'users'])->get();
        
        // Get all engagements for this organization's projects
        $allEngagements = \App\Models\Engagement::whereIn('project_id', $projects->pluck('id'))
            ->with(['activityType.contactFamily', 'user', 'project'])
            ->orderByDesc('engagement_date')
            ->get();
        
        // Recent engagements (last 5)
        $recentEngagements = $allEngagements->take(5);
        
        // Unique team members across all projects
        $teamMembers = $projects->pluck('users')->flatten()->unique('id')->sortBy('name');
        
        // YTD engagements
        $ytdEngagements = $allEngagements->filter(fn($e) => $e->engagement_date->year === now()->year);
        
        // YTD totals
        $ytdTotals = [
            'engagements' => $ytdEngagements->count(),
            'hours' => $ytdEngagements->sum(fn($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
            'participants' => $ytdEngagements->sum('participant_count'),
        ];
        
        // Breakdown by contact family
        $contactFamilyBreakdown = $ytdEngagements->groupBy(fn($e) => $e->activityType->contactFamily->name)
            ->map(fn($group) => $group->count())
            ->sortDesc();
        
        return view('organizations.show', compact(
            'organization',
            'projects',
            'recentEngagements',
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

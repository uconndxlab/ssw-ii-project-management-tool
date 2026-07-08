<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Models\State;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $states = State::orderBy('name', 'asc')->get(['id', 'name']);

        $query = Organization::with(['states', 'projects', 'programs'])->withCount('agreements');

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('states', function ($stateQuery) use ($search) {
                        $stateQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter
        if ($request->filled('state_id')) {
            $query->whereHas('states', fn ($q) => $q->where('states.id', $request->integer('state_id')));
        }

        $query->orderBy('name');

        $organizations = $query->paginate(20)->withQueryString();

        // HTMX: filters only
        if ($request->header('HX-Request') === 'true' && $request->input('partial') === 'filters') {
            return view('organizations.partials.filters', compact('states'));
        }

        // HTMX: table only
        if ($request->header('HX-Request') === 'true') {
            return view('organizations.partials.table', compact('organizations'));
        }

        return view('organizations.index', compact('organizations', 'states'));
    }

    public function show(Organization $organization)
    {
        // Load agreements with relationships
        $agreements = $organization->agreements()->with(['states', 'users'])->get();

        // Get all activities for this organization's agreements
        $allActivities = \App\Models\Activity::whereHas('agreements', function($query) use ($agreements) {
                $query->whereIn('agreements.id', $agreements->pluck('id'));
            })
            ->with(['activityType.contactFamily', 'user', 'agreements'])
            ->orderByDesc('engagement_date')
            ->get();

        // Recent activities (last 5)
        $recentActivities = $allActivities->take(5);

        // Deduplicate staff across all agreements, collecting agreement names per user
        $teamMembersMap = [];
        foreach ($agreements as $agreement) {
            foreach ($agreement->users as $user) {
                if (!isset($teamMembersMap[$user->id])) {
                    $teamMembersMap[$user->id] = clone $user;
                    $teamMembersMap[$user->id]->via_agreements = collect();
                }
                $teamMembersMap[$user->id]->via_agreements->push($agreement->name);
            }
        }
        $teamMembers = collect($teamMembersMap)->sortBy('name');

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
        $states = State::orderBy('name', 'asc')->get();
        $projects = Project::with(['programs' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('organizations.create', compact('states', 'projects'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'state_ids' => ['required', 'array', 'min:1'],
            'state_ids.*' => ['exists:states,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['exists:programs,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['exists:projects,id'],
        ]);

        $organization = Organization::create(['name' => $validated['name']]);
        $organization->states()->sync($validated['state_ids']);
        $organization->programs()->sync($validated['program_ids'] ?? []);
        $organization->projects()->sync($validated['project_ids'] ?? []);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function edit(Organization $organization)
    {
        $states = State::orderBy('name', 'asc')->get();
        $projects = Project::with(['programs' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('organizations.edit', compact('organization', 'states', 'projects'));
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'state_ids' => ['required', 'array', 'min:1'],
            'state_ids.*' => ['exists:states,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['exists:programs,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['exists:projects,id'],
        ]);

        $organization->update(['name' => $validated['name']]);
        $organization->states()->sync($validated['state_ids']);
        $organization->programs()->sync($validated['program_ids'] ?? []);
        $organization->projects()->sync($validated['project_ids'] ?? []);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization updated successfully.');
    }

    public function destroy(Organization $organization)
    {
        Organization::destroy($organization->id);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization deleted successfully.');
    }
}

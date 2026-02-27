<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Models\State;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProjectController extends Controller
{
    public function index()
    {
        $query = Project::with(['organization', 'state', 'users']);

        // Visibility enforcement: non-admins only see assigned projects
        if (!Auth::user()->isAdmin()) {
            $query->whereHas('users', function ($q) {
                $q->where('user_id', Auth::id());
            });
        }

        $projects = $query->orderBy('created_at', 'desc')->paginate(20);
        
        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create projects.');

        $states = State::orderBy('name')->get();
        $organizations = Organization::orderBy('name')->get();
        $users = User::orderBy('name')->get();
        
        return view('projects.create', compact('states', 'organizations', 'users'));
    }

    public function store(Request $request)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create projects.');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'state_id' => ['required', 'exists:states,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'organization_id' => $validated['organization_id'],
            'state_id' => $validated['state_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        if (!empty($validated['user_ids'])) {
            $project->users()->sync($validated['user_ids']);
        }

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project created successfully.');
    }

    public function show(Project $project)
    {
        // Visibility enforcement
        if (!Auth::user()->isAdmin() && !$project->users->contains(Auth::id())) {
            abort(403, 'Unauthorized access to this project.');
        }

        $project->load(['organization', 'state', 'users']);
        
        // Get engagements for this project
        $engagements = $project->engagements()
            ->with(['activityType.contactFamily', 'user', 'participants'])
            ->orderBy('engagement_date', 'desc')
            ->get();
        
        // Recent engagements (last 10)
        $recentEngagements = $engagements->take(10);
        
        // Programs represented in engagements
        $programs = $project->engagements()
            ->with('programs')
            ->get()
            ->pluck('programs')
            ->flatten()
            ->unique('id')
            ->sortBy('name');
        
        // Lifetime totals
        $lifetimeTotals = [
            'engagements' => $engagements->count(),
            'hours' => $engagements->sum(fn($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
            'participants' => $engagements->sum('participant_count'),
        ];
        
        // YTD totals (current year)
        $ytdEngagements = $engagements->filter(fn($e) => $e->engagement_date->year === now()->year);
        $ytdTotals = [
            'engagements' => $ytdEngagements->count(),
            'hours' => $ytdEngagements->sum(fn($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
            'participants' => $ytdEngagements->sum('participant_count'),
        ];
        
        return view('projects.show', compact('project', 'recentEngagements', 'programs', 'lifetimeTotals', 'ytdTotals'));
    }

    public function edit(Project $project)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can edit projects.');

        $states = State::orderBy('name')->get();
        $organizations = Organization::orderBy('name')->get();
        $users = User::orderBy('name')->get();
        $project->load('users');
        
        return view('projects.edit', compact('project', 'states', 'organizations', 'users'));
    }

    public function update(Request $request, Project $project)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can update projects.');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'state_id' => ['required', 'exists:states,id'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $project->update([
            'name' => $validated['name'],
            'organization_id' => $validated['organization_id'],
            'state_id' => $validated['state_id'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
        ]);

        $project->users()->sync($validated['user_ids'] ?? []);

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can delete projects.');

        $project->delete();

        return redirect()
            ->route('projects.index')
            ->with('success', 'Project deleted successfully.');
    }

    // HTMX endpoint for user assignment
    public function assignUser(Request $request, Project $project)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $project->users()->attach($validated['user_id']);
        $project->load('users');

        return view('projects.partials.user-list', compact('project'));
    }

    public function removeUser(Request $request, Project $project, User $user)
    {
        $project->users()->detach($user->id);
        $project->load('users');

        return view('projects.partials.user-list', compact('project'));
    }
}

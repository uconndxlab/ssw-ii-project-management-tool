<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can manage teams.');

        $query = Team::withCount('users');

        // Filter by active status
        if ($request->filled('active')) {
            $query->where('active', $request->input('active') === '1');
        }

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where('name', 'like', "%{$search}%");
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'members':
                $query->orderBy('users_count', $direction);
                break;
            case 'active':
                $query->orderBy('active', $direction);
                break;
            default:
                $query->orderBy('name', $direction);
        }

        $teams = $query->paginate(20)->withQueryString();

        if ($request->header('HX-Request')) {
            return view('teams.partials.table', compact('teams', 'sort', 'direction'));
        }

        return view('teams.index', compact('teams', 'sort', 'direction'));
    }

    public function create()
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create teams.');

        $users = User::query()->orderBy('name', 'asc')->get();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();

        return view('teams.create', compact('users', 'projects'));
    }

    public function store(Request $request)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create teams.');

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            ProjectProgramScope::validateSelection(
                $validator,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
        });

        $validated = $validator->validate();

        $team = Team::create([
            'name' => $validated['name'],
            'active' => $validated['active'],
        ]);

        $team->users()->sync($validated['user_ids'] ?? []);
        $team->projects()->sync(ProjectProgramScope::normalizeIds($validated['project_ids'] ?? []));
        $team->programs()->sync(ProjectProgramScope::normalizeIds($validated['program_ids'] ?? []));

        return redirect()
            ->route('teams.index')
            ->with('success', 'Team created successfully.');
    }

    public function show(Team $team)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can view teams.');

        $team->load([
            'users',
            'agreements.deliverables.activityType',
            'agreements.deliverables.contactFamily',
            'agreements.deliverables.assignedUsers',
        ]);

        // Build per-member deliverable map from already-loaded data (no extra queries)
        $memberDeliverables = [];
        foreach ($team->users as $user) {
            $memberDeliverables[$user->id] = [];
            foreach ($team->agreements as $agreement) {
                foreach ($agreement->deliverables as $deliverable) {
                    if ($deliverable->assignedUsers->contains('id', $user->id)) {
                        $memberDeliverables[$user->id][] = [
                            'deliverable' => $deliverable,
                            'agreement'   => $agreement,
                        ];
                    }
                }
            }
        }

        return view('teams.show', compact('team', 'memberDeliverables'));
    }

    public function edit(Team $team)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can edit teams.');

        $users = User::query()->orderBy('name', 'asc')->get();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();
        $team->load(['users', 'projects', 'programs']);

        return view('teams.edit', compact('team', 'users', 'projects'));
    }

    public function update(Request $request, Team $team)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can update teams.');

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            ProjectProgramScope::validateSelection(
                $validator,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
        });

        $validated = $validator->validate();

        $team->update([
            'name' => $validated['name'],
            'active' => $validated['active'],
        ]);

        $team->users()->sync($validated['user_ids'] ?? []);
        $team->projects()->sync(ProjectProgramScope::normalizeIds($validated['project_ids'] ?? []));
        $team->programs()->sync(ProjectProgramScope::normalizeIds($validated['program_ids'] ?? []));

        return redirect()
            ->route('teams.index')
            ->with('success', 'Team updated successfully.');
    }

    public function destroy(Team $team)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can delete teams.');

        Team::destroy($team->id);

        return redirect()
            ->route('teams.index')
            ->with('success', 'Team deleted successfully.');
    }
}

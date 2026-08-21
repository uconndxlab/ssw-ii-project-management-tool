<?php

namespace App\Http\Controllers;

use App\Enums\ProgramScopeMode;
use App\Models\Program;
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

        $query = Team::query()
            ->withCount('users')
            ->with([
                'programs.projects:id,name',
            ]);

        // Filter by active status
        if ($request->filled('active')) {
            $query->where('active', $request->input('active') === '1');
        }

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('programs.projects', fn ($relation) => $relation->where('projects.id', $projectId))
                    ->orWhere('teams.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhere('teams.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->whereIlike('name', "%{$search}%");
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $this->applyTeamIndexSort($query, $sort, $direction);

        $teams = $query->paginate(20)->withQueryString();

        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);

        if ($request->header('HX-Request')) {
            return view('teams.partials.table', compact('teams', 'sort', 'direction'));
        }

        return view('teams.index', compact('teams', 'sort', 'direction', 'filterProjects', 'filterPrograms'));
    }

    private function applyTeamIndexSort($query, string $sort, string $direction): void
    {
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        match ($sort) {
            'members' => $query->orderBy('users_count', $direction),
            'active' => $query->orderBy('teams.active', $direction)->orderBy('teams.name', 'asc'),
            'projects' => $query->orderByRaw($this->minTeamProjectNameSql()." {$dir}"),
            'programs' => $query->orderByRaw($this->minTeamProgramNameSql()." {$dir}"),
            default => $query->orderBy('teams.name', $direction),
        };
    }

    private function minTeamProjectNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM projects p
            INNER JOIN program_project pp ON pp.project_id = p.id
            INNER JOIN team_program tp ON tp.program_id = pp.program_id AND tp.team_id = teams.id
        ), '')";
    }

    private function minTeamProgramNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM programs p
            INNER JOIN team_program tp ON tp.program_id = p.id AND tp.team_id = teams.id
        ), '')";
    }

    public function create()
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create teams.');

        $users = User::query()->active()->orderBy('name', 'asc')->get();
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
            'program_scope_mode' => ['required', 'in:all,specific,none'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            ProjectProgramScope::validateModeSelection(
                $validator,
                $request->input('program_scope_mode', ProgramScopeMode::Specific->value),
                Team::class,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
        });

        $validated = $validator->validate();
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, Team::class)->value;

        $team = Team::create([
            'name' => $validated['name'],
            'active' => $validated['active'],
            'program_scope_mode' => $validated['program_scope_mode'],
        ]);

        $team->users()->sync($validated['user_ids'] ?? []);
        $team->programs()->sync(ProjectProgramScope::modeAwareProgramIds(
            $validated['program_scope_mode'],
            Team::class,
            $validated['project_ids'] ?? [],
            $validated['program_ids'] ?? []
        ));

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
            'agreements.deliverables.users',
            'agreements.deliverables.teams',
        ]);

        $team->setRelation(
            'agreements',
            $team->agreements->where('active', true)->values()
        );

        // Build per-member deliverable map from already-loaded data (no extra queries)
        $memberDeliverables = [];
        foreach ($team->users as $user) {
            $memberDeliverables[$user->id] = [];
            foreach ($team->agreements as $agreement) {
                foreach ($agreement->deliverables as $deliverable) {
                    if ($deliverable->users->contains(fn ($assignedUser) => (int) $assignedUser->id === (int) $user->id && !$assignedUser->pivot->unassigned_at)) {
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

        $users = User::query()->active()->orderBy('name', 'asc')->get();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();
        $team->load(['users', 'programs.projects']);

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
            'program_scope_mode' => ['required', 'in:all,specific,none'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            ProjectProgramScope::validateModeSelection(
                $validator,
                $request->input('program_scope_mode', ProgramScopeMode::Specific->value),
                Team::class,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
        });

        $validated = $validator->validate();
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, Team::class)->value;

        $team->update([
            'name' => $validated['name'],
            'active' => $validated['active'],
            'program_scope_mode' => $validated['program_scope_mode'],
        ]);

        $team->users()->sync($validated['user_ids'] ?? []);
        $team->programs()->sync(ProjectProgramScope::modeAwareProgramIds(
            $validated['program_scope_mode'],
            Team::class,
            $validated['project_ids'] ?? [],
            $validated['program_ids'] ?? []
        ));

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

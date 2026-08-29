<?php

namespace App\Http\Controllers;

use App\Enums\AccessProfile;
use App\Enums\ProgramScopeMode;
use App\Http\Requests\AdminUserRequest;
use App\Models\Program;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Services\UserDeactivationService;
use App\Services\UserShowPageData;
use App\Support\Authorization\PrivilegeSync;
use App\Support\Authorization\ScopeSync;
use App\Support\Authorization\UserAccess;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminUserController extends Controller
{
    public function __construct(
        private UserDeactivationService $userDeactivationService,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        $query = User::query()->visibleTo(Auth::user())->with([
            'supervisor:id,name',
            'programs.projects:id,name',
            'teams.programs.projects:id,name',
            'privileges',
        ]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereIlike('name', "%{$search}%")
                  ->orWhereIlike('email', "%{$search}%")
                  ->orWhereIlike('po_number', "%{$search}%");
            });
        }

        if ($request->filled('access_profile')) {
            $query->where('access_profile', $request->input('access_profile'));
        }

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('programs.projects', fn ($relation) => $relation->where('projects.id', $projectId))
                    ->orWhere('users.program_scope_mode', ProgramScopeMode::All->value)
                    ->orWhereHas('teams', function ($teamQuery) use ($projectId) {
                        $teamQuery->where('teams.program_scope_mode', ProgramScopeMode::All->value)
                            ->orWhereHas('programs.projects', fn ($relation) => $relation->where('projects.id', $projectId));
                    });
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhere('users.program_scope_mode', ProgramScopeMode::All->value)
                    ->orWhereHas('teams', function ($teamQuery) use ($programId) {
                        $teamQuery->where('teams.program_scope_mode', ProgramScopeMode::All->value)
                            ->orWhereHas('programs', fn ($relation) => $relation->where('programs.id', $programId));
                    });
            });
        }

        $status = $request->input('status');
        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        $sort      = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $this->applyUserIndexSort($query, $sort, $direction);

        $users = $query->paginate(20)->withQueryString();

        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);

        if ($request->header('HX-Request')) {
            return view('admin.users.partials.table', compact('users', 'sort', 'direction'));
        }

        return view('admin.users.index', compact('users', 'sort', 'direction', 'filterProjects', 'filterPrograms'));
    }

    private function applyUserIndexSort($query, string $sort, string $direction): void
    {
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        match ($sort) {
            'email' => $query->orderBy('users.email', $direction),
            'po' => $query->orderByRaw("COALESCE(users.po_number, '') {$dir}")->orderBy('users.name', 'asc'),
            'access_profile' => $query->orderBy('users.access_profile', $direction),
            'supervisor' => $query->orderBy(
                User::query()->select('name')->whereColumn('id', 'users.supervisor_id'),
                $direction
            ),
            'active' => $query->orderBy('users.active', $direction)->orderBy('users.name', $direction),
            'projects' => $query->orderByRaw($this->minAssignedProjectNameSql()." {$dir}"),
            'programs' => $query->orderByRaw($this->minAssignedProgramNameSql()." {$dir}"),
            default => $query->orderBy('users.name', $direction),
        };
    }

    private function minAssignedProjectNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(sorted.name) FROM (
                SELECT p.name
                FROM projects p
                INNER JOIN program_project pp ON pp.project_id = p.id
                INNER JOIN user_program up ON up.program_id = pp.program_id AND up.user_id = users.id
                UNION
                SELECT p.name
                FROM projects p
                INNER JOIN program_project pp ON pp.project_id = p.id
                INNER JOIN team_program tp ON tp.program_id = pp.program_id
                INNER JOIN team_user tu ON tu.team_id = tp.team_id AND tu.user_id = users.id
            ) AS sorted
        ), '')";
    }

    private function minAssignedProgramNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(sorted.name) FROM (
                SELECT p.name
                FROM programs p
                INNER JOIN user_program up ON up.program_id = p.id AND up.user_id = users.id
                UNION
                SELECT p.name
                FROM programs p
                INNER JOIN team_program tp ON tp.program_id = p.id
                INNER JOIN team_user tu ON tu.team_id = tp.team_id AND tu.user_id = users.id
            ) AS sorted
        ), '')";
    }

    public function supervisees(Request $request)
    {
        $this->authorize('viewSupervisees', User::class);

        $query = User::query()->with([
            'supervisor:id,name',
            'programs.projects:id,name',
            'teams.programs.projects:id,name',
        ]);
        UserAccess::for(Auth::user())->applySuperviseesVisibility($query);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->whereIlike('name', "%{$search}%")
                  ->orWhereIlike('email', "%{$search}%")
                  ->orWhereIlike('po_number', "%{$search}%");
            });
        }

        $sort      = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';
        $this->applyUserIndexSort($query, $sort, $direction);

        $users = $query->paginate(20)->withQueryString();
        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $superviseesIndex = true;

        if ($request->header('HX-Request')) {
            return view('admin.users.partials.table', compact('users', 'sort', 'direction', 'superviseesIndex'));
        }

        return view('admin.users.index', compact('users', 'sort', 'direction', 'filterProjects', 'filterPrograms', 'superviseesIndex'));
    }

    public function create()
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', $this->userFormData(new User()));
    }

    public function store(AdminUserRequest $request)
    {
        $validated = $request->validated();
        $isActive = $request->boolean('active');

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'access_profile' => AccessProfile::Member,
            'is_supervisor' => false,
            'po_number' => $validated['po_number'] ?? null,
            'active' => $isActive,
            'supervisor_id' => $validated['supervisor_id'] ?? null,
            'program_scope_mode' => ProgramScopeMode::Specific,
        ]);

        if ($isActive) {
            $this->syncScopeAssignments($user, $validated);
            if (! empty($validated['team_ids'])) {
                $user->teams()->sync($validated['team_ids']);
            }
        } else {
            $this->userDeactivationService->revokeMembership($user);
        }

        $this->syncPrivileges($user, $validated, $request);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', $this->userFormData($user));
    }

    public function update(AdminUserRequest $request, User $user)
    {
        $validated = $request->validated();
        $wasActive = $user->isActive();
        $isActive = $request->boolean('active');

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'po_number' => $validated['po_number'] ?? null,
            'active' => $isActive,
            'supervisor_id' => $validated['supervisor_id'] ?? null,
        ];

        if (!empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        $user->update($attributes);

        if (!$isActive) {
            if ($wasActive) {
                $this->userDeactivationService->revokeMembership($user);
            }
        } else {
            $this->syncScopeAssignments($user, $validated);
        }

        $this->syncPrivileges($user, $validated, $request);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);

        return view('admin.users.show', [
            ...UserShowPageData::for($user),
            'isProfile' => false,
            'canEditUser' => Auth::user()->can('update', $user),
        ]);
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);

        User::destroy($user->id);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    private function supervisorOptions(?User $user = null)
    {
        $query = User::query()->active()->where('is_supervisor', true)->orderBy('name', 'asc');

        if ($user?->exists) {
            $query->whereKeyNot($user->id);
        }

        return $query->get();
    }

    private function userFormData(User $user): array
    {
        $user->loadMissing(['programs.projects', 'privileges']);

        $projects = ProjectProgramScope::assignableProjectsWithProgramsFor(Auth::user(), $user->exists ? $user : null);

        $teams = Team::query()->orderBy('name')->get(['id', 'name']);
        $access = UserAccess::for(Auth::user());
        $ledgerProjects = Project::query()->orderBy('name');
        $ledgerPrograms = Program::query()->orderBy('name');
        if (! $access->isSystemAdmin()) {
            $ledgerProjects->whereIn('id', $access->adminProjectIds() ?: [0]);
            $ledgerPrograms->whereIn('id', $access->adminProgramIds() ?: [0]);
        }

        return [
            'user' => $user,
            'supervisors' => $this->supervisorOptions($user->exists ? $user : null),
            'projects' => $projects,
            'teams' => $teams,
            'ledgerProjects' => $ledgerProjects->get(['id', 'name']),
            'ledgerPrograms' => $ledgerPrograms->get(['id', 'name']),
        ];
    }

    private function syncScopeAssignments(User $user, array $validated): void
    {
        ScopeSync::applyTo(
            Auth::user(),
            $user,
            ProgramScopeMode::from($validated['program_scope_mode'] ?? ProgramScopeMode::Specific->value),
            $validated['program_ids'] ?? [],
        );
    }

    private function syncPrivileges(User $user, array $validated, Request $request): void
    {
        $user->load('privileges');
        $profile = AccessProfile::from($validated['access_profile']);
        PrivilegeSync::apply(
            Auth::user(),
            $user,
            $profile,
            $request->boolean('is_supervisor') && $profile !== AccessProfile::Input,
            PrivilegeSync::rowsFromRequest($request->all(), Auth::user()),
        );
    }
}

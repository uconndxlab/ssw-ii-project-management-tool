<?php

namespace App\Http\Controllers;

use App\Http\Requests\AdminUserRequest;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use App\Services\UserDeactivationService;
use App\Support\UserDeliverableReporting;
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
        $query = User::query()->with([
            'supervisor:id,name',
            'projects:id,name',
            'programs:id,name,project_id',
            'teams.projects:id,name',
            'teams.programs:id,name,project_id',
        ]);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('projects', fn ($relation) => $relation->where('projects.id', $projectId))
                    ->orWhereHas('teams.projects', fn ($relation) => $relation->where('projects.id', $projectId));
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhereHas('teams.programs', fn ($relation) => $relation->where('programs.id', $programId));
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
            'role' => $query->orderBy('users.role', $direction),
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
                INNER JOIN user_project up ON up.project_id = p.id AND up.user_id = users.id
                UNION
                SELECT p.name
                FROM projects p
                INNER JOIN team_project tp ON tp.project_id = p.id
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

    public function create()
    {
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
            'role' => $validated['role'],
            'active' => $isActive,
            'supervisor_id' => $validated['supervisor_id'] ?? null,
        ]);

        if ($isActive) {
            $this->syncScopeAssignments($user, $validated);
        } else {
            $this->userDeactivationService->revokeMembership($user);
        }

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
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
            'role' => $validated['role'],
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

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function show(User $user)
    {
        $user->load([
            'supervisor',
            'projects',
            'programs.project',
            'agreements.organizations',
            'agreements.states',
            'teams.projects',
            'teams.programs.project',
            'teams.agreements.organizations',
            'teams.agreements.states',
        ]);

        $scopeBySource = $user->getScopeBySource();
        $agreementReports = UserDeliverableReporting::buildAgreementReports($user);

        $recentActivities = $user->activities()
            ->with(['activityType', 'agreements'])
            ->orderByDesc('engagement_date')
            ->take(10)
            ->get();

        return view('admin.users.show', compact('user', 'recentActivities', 'scopeBySource', 'agreementReports'));
    }

    public function destroy(User $user)
    {
        if (Auth::id() === $user->id) {
            return redirect()
                ->route('admin.users.index')
                ->with('error', 'You cannot delete your own user account.');
        }

        User::destroy($user->id);

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    private function supervisorOptions(?User $user = null)
    {
        $query = User::query()->active()->orderBy('name', 'asc');

        if ($user?->exists) {
            $query->whereKeyNot($user->id);
        }

        return $query->get();
    }

    private function userFormData(User $user): array
    {
        $user->loadMissing(['projects', 'programs.project']);

        $selectedProjectIds = $user->projects->pluck('id');
        $selectedProgramIds = $user->programs->pluck('id');

        $projects = Project::query()
            ->where(function ($query) use ($selectedProjectIds) {
                $query->where('active', true);

                if ($selectedProjectIds->isNotEmpty()) {
                    $query->orWhereIn('id', $selectedProjectIds);
                }
            })
            ->with(['programs' => function ($query) use ($selectedProgramIds) {
                $query->where(function ($programQuery) use ($selectedProgramIds) {
                    $programQuery->where('active', true);

                    if ($selectedProgramIds->isNotEmpty()) {
                        $programQuery->orWhereIn('id', $selectedProgramIds);
                    }
                })->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        return [
            'user' => $user,
            'supervisors' => $this->supervisorOptions($user->exists ? $user : null),
            'projects' => $projects,
        ];
    }

    private function syncScopeAssignments(User $user, array $validated): void
    {
        $user->projects()->sync($validated['project_ids'] ?? []);
        $user->programs()->sync($validated['program_ids'] ?? []);
    }
}

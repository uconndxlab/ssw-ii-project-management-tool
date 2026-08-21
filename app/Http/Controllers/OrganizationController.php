<?php

namespace App\Http\Controllers;

use App\Enums\ProgramScopeMode;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\State;
use App\Models\User;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $states = State::orderBy('name', 'asc')->get(['id', 'name']);

        $query = Organization::query()
            ->with([
                'states:id,name',
                'programs.projects:id,name',
            ])
            ->withCount('agreements');

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereIlike('name', "%{$search}%")
                    ->orWhereIlike('po_number', "%{$search}%")
                    ->orWhereHas('states', function ($stateQuery) use ($search) {
                        $stateQuery->whereIlike('name', "%{$search}%");
                    });
            });
        }

        // Filter
        if ($request->filled('state_id')) {
            $query->whereHas('states', fn ($q) => $q->where('states.id', $request->integer('state_id')));
        }

        $status = $request->input('status');
        if ($status === 'active') {
            $query->where('active', true);
        } elseif ($status === 'inactive') {
            $query->where('active', false);
        }

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('programs.projects', fn ($relation) => $relation->where('projects.id', $projectId))
                    ->orWhere('organizations.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhere('organizations.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $this->applyOrganizationIndexSort($query, $sort, $direction);

        $organizations = $query->paginate(20)->withQueryString();

        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);

        // HTMX: filters only
        if ($request->header('HX-Request') === 'true' && $request->input('partial') === 'filters') {
            return view('organizations.partials.filters', compact('states', 'filterProjects', 'filterPrograms', 'sort', 'direction'));
        }

        // HTMX: table only
        if ($request->header('HX-Request') === 'true') {
            return view('organizations.partials.table', compact('organizations', 'sort', 'direction'));
        }

        return view('organizations.index', compact(
            'organizations',
            'states',
            'filterProjects',
            'filterPrograms',
            'sort',
            'direction',
        ));
    }

    public function show(Organization $organization)
    {
        $organization->load(['states', 'programs.projects', 'users']);

        // Load agreements with relationships
        $agreements = $organization->agreements()->active()->with(['states', 'users'])->get();

        // Get all activities for this organization's agreements
        $allActivities = \App\Models\Activity::whereHas('agreements', function ($query) use ($agreements) {
            $query->whereIn('agreements.id', $agreements->pluck('id'));
        })
            ->with(['activityType.contactFamily', 'user', 'agreements'])
            ->orderByRecentDisplay()
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
        $ytdActivities = $allActivities->filter(fn ($e) => $e->engagement_date->year === now()->year);

        // YTD totals
        $ytdTotals = [
            'activities' => $ytdActivities->count(),
            'hours' => $ytdActivities->sum(fn ($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
            'participants' => $ytdActivities->sum('participant_count'),
        ];

        // Breakdown by contact family
        $contactFamilyBreakdown = $ytdActivities->groupBy(fn ($e) => $e->activityType->contactFamily->name)
            ->map(fn ($group) => $group->count())
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
        $projects = ProjectProgramScope::activeProjectsWithPrograms();
        $users = User::query()->active()->orderBy('name', 'asc')->get();

        return view('organizations.create', compact('states', 'projects', 'users'));
    }

    public function store(Request $request)
    {
        $validated = $this->validateOrganization($request);

        $organization = Organization::create([
            'name' => $validated['name'],
            'po_number' => $validated['po_number'] ?? null,
            'active' => $request->boolean('active'),
            'program_scope_mode' => $validated['program_scope_mode'],
        ]);
        $organization->states()->sync($validated['state_ids']);
        $organization->programs()->sync(ProjectProgramScope::modeAwareProgramIds(
            $validated['program_scope_mode'],
            Organization::class,
            $validated['project_ids'] ?? [],
            $validated['program_ids'] ?? []
        ));
        $organization->users()->sync($validated['user_ids'] ?? []);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function edit(Organization $organization)
    {
        $organization->load(['states', 'programs.projects', 'users']);
        $states = State::orderBy('name', 'asc')->get();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();
        $users = User::query()->active()->orderBy('name', 'asc')->get();

        return view('organizations.edit', compact('organization', 'states', 'projects', 'users'));
    }

    public function update(Request $request, Organization $organization)
    {
        $validated = $this->validateOrganization($request, $organization);

        $organization->update([
            'name' => $validated['name'],
            'po_number' => $validated['po_number'] ?? null,
            'active' => $request->boolean('active'),
            'program_scope_mode' => $validated['program_scope_mode'],
        ]);
        $organization->states()->sync($validated['state_ids']);
        $organization->programs()->sync(ProjectProgramScope::modeAwareProgramIds(
            $validated['program_scope_mode'],
            Organization::class,
            $validated['project_ids'] ?? [],
            $validated['program_ids'] ?? []
        ));
        $organization->users()->sync($validated['user_ids'] ?? []);

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

    private function validateOrganization(Request $request, ?Organization $organization = null): array
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'po_number' => [
                'nullable',
                'string',
                'size:6',
                'regex:/^[0-9]{6}$/',
                Rule::unique('organizations', 'po_number')->ignore($organization?->id),
            ],
            'active' => ['nullable', 'boolean'],
            'state_ids' => ['required', 'array', 'min:1'],
            'state_ids.*' => ['exists:states,id'],
            'program_scope_mode' => ['required', Rule::in(ProgramScopeMode::values())],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ], [
            'po_number.regex' => 'The PO number must be exactly 6 digits.',
            'po_number.size' => 'The PO number must be exactly 6 digits.',
            'po_number.unique' => 'This PO number is already assigned to another organization.',
        ]);

        $validator->after(function ($validator) use ($request) {
            ProjectProgramScope::validateModeSelection(
                $validator,
                $request->input('program_scope_mode', ProgramScopeMode::Specific->value),
                Organization::class,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
        });

        $validated = $validator->validate();
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, Organization::class)->value;

        return $validated;
    }

    private function applyOrganizationIndexSort($query, string $sort, string $direction): void
    {
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        match ($sort) {
            'po' => $query->orderByRaw("COALESCE(organizations.po_number, '') {$dir}")->orderBy('organizations.name', 'asc'),
            'states' => $query->orderByRaw($this->minOrganizationStateNameSql()." {$dir}")->orderBy('organizations.name', 'asc'),
            'projects' => $query->orderByRaw($this->minOrganizationProjectNameSql()." {$dir}")->orderBy('organizations.name', 'asc'),
            'programs' => $query->orderByRaw($this->minOrganizationProgramNameSql()." {$dir}")->orderBy('organizations.name', 'asc'),
            'status', 'active' => $query->orderBy('organizations.active', $direction)->orderBy('organizations.name', 'asc'),
            'agreements' => $query->orderBy('agreements_count', $direction)->orderBy('organizations.name', 'asc'),
            'created' => $query->orderBy('organizations.created_at', $direction)->orderBy('organizations.name', 'asc'),
            default => $query->orderBy('organizations.name', $direction),
        };
    }

    private function minOrganizationStateNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(s.name)
            FROM states s
            INNER JOIN organization_state os ON os.state_id = s.id AND os.organization_id = organizations.id
        ), '')";
    }

    private function minOrganizationProjectNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM projects p
            INNER JOIN program_project pp ON pp.project_id = p.id
            INNER JOIN organization_program op ON op.program_id = pp.program_id AND op.organization_id = organizations.id
        ), '')";
    }

    private function minOrganizationProgramNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM programs p
            INNER JOIN organization_program op ON op.program_id = p.id AND op.organization_id = organizations.id
        ), '')";
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\ProgramScopeMode;
use App\Models\Activity;
use App\Models\ContactFamily;
use App\Models\Organization;
use App\Models\OrganizationContact;
use App\Models\Program;
use App\Models\Project;
use App\Models\State;
use App\Models\User;
use App\Support\Authorization\ScopeSync;
use App\Support\CarbonDate;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Organization::class);

        $states = State::orderBy('name', 'asc')->get(['id', 'name']);

        $query = Organization::query()
            ->visibleTo($this->actor())
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
        $this->authorize('view', $organization);
        $organization->load(['states', 'programs.projects', 'users', 'contacts']);

        // Load agreements with relationships
        $agreements = $organization->agreements()->active()->with(['states', 'users'])->get();

        // Get all activities for this organization's agreements
        $allActivities = Activity::whereHas('agreements', function ($query) use ($agreements) {
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
                if (! isset($teamMembersMap[$user->id])) {
                    $teamMembersMap[$user->id] = clone $user;
                    $teamMembersMap[$user->id]->via_agreements = collect();
                }
                if ($teamMembersMap[$user->id]->via_agreements === null) {
                    $teamMembersMap[$user->id]->via_agreements = collect();
                }
                $teamMembersMap[$user->id]->via_agreements->push($agreement->name);
            }
        }
        $teamMembers = collect($teamMembersMap)->sortBy('name');

        // YTD activities
        $ytdActivities = $allActivities->filter(fn ($e) => CarbonDate::parse($e->engagement_date)?->year === now()->year);

        // YTD totals
        $ytdTotals = [
            'activities' => $ytdActivities->count(),
            'hours' => $ytdActivities->sum(fn ($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
            'participants' => $ytdActivities->sum('participant_count'),
        ];

        // Breakdown by contact family
        $contactFamilyBreakdown = $ytdActivities
            ->groupBy(function ($e) {
                $contactFamily = $e->activityType?->contactFamily;

                return $contactFamily instanceof ContactFamily ? $contactFamily->name : 'Unknown';
            })
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
        $this->authorize('create', Organization::class);
        $states = State::orderBy('name', 'asc')->get();
        $projects = ProjectProgramScope::assignableProjectsWithProgramsFor($this->actor());
        $users = User::query()->active()->orderBy('name', 'asc')->get();

        return view('organizations.create', compact('states', 'projects', 'users'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Organization::class);

        $validated = $this->validateOrganization($request);

        $organization = Organization::create([
            'name' => $validated['name'],
            'po_number' => $validated['po_number'] ?? null,
            'active' => $request->boolean('active'),
            'program_scope_mode' => ProgramScopeMode::Specific,
        ]);
        $organization->states()->sync($validated['state_ids']);
        ScopeSync::applyTo(
            $this->actor(),
            $organization,
            ProgramScopeMode::from($validated['program_scope_mode']),
            $validated['program_ids'] ?? [],
        );
        $organization->users()->sync($validated['user_ids'] ?? []);
        $this->syncOrganizationContacts($organization, $validated['contacts'] ?? []);

        return redirect()
            ->route('organizations.index')
            ->with('success', 'Organization created successfully.');
    }

    public function edit(Organization $organization)
    {
        $this->authorize('update', $organization);
        $organization->load(['states', 'programs.projects', 'users', 'contacts']);
        $states = State::orderBy('name', 'asc')->get();
        $projects = ProjectProgramScope::assignableProjectsWithProgramsFor($this->actor(), $organization);
        $users = User::query()->active()->orderBy('name', 'asc')->get();

        return view('organizations.edit', compact('organization', 'states', 'projects', 'users'));
    }

    public function update(Request $request, Organization $organization)
    {
        $this->authorize('update', $organization);

        $validated = $this->validateOrganization($request, $organization);

        $organization->update([
            'name' => $validated['name'],
            'po_number' => $validated['po_number'] ?? null,
            'active' => $request->boolean('active'),
        ]);
        $organization->states()->sync($validated['state_ids']);
        ScopeSync::applyTo(
            $this->actor(),
            $organization,
            ProgramScopeMode::from($validated['program_scope_mode']),
            $validated['program_ids'] ?? [],
        );
        $organization->users()->sync($validated['user_ids'] ?? []);
        $this->syncOrganizationContacts($organization, $validated['contacts'] ?? []);

        return $this->redirectAfterSave($organization, 'Organization updated successfully.');
    }

    public function destroy(Organization $organization)
    {
        $this->authorize('delete', $organization);
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
            'contacts' => ['nullable', 'array', 'max:10'],
            'contacts.*.id' => ['nullable', 'integer'],
            'contacts.*._delete' => ['nullable', 'boolean'],
            'contacts.*.name' => ['nullable', 'string', 'max:255'],
            'contacts.*.title' => ['nullable', 'string', 'max:255'],
            'contacts.*.email' => ['nullable', 'string', 'email', 'max:255'],
            'contacts.*.phone' => ['nullable', 'string', 'max:30'],
            'contacts.*.is_primary' => ['nullable', 'boolean'],
        ], [
            'po_number.regex' => 'The PO number must be exactly 6 digits.',
            'po_number.size' => 'The PO number must be exactly 6 digits.',
            'po_number.unique' => 'This PO number is already assigned to another organization.',
            'contacts.max' => 'Organizations may have at most 10 contacts.',
        ]);

        $validator->after(function ($validator) use ($request, $organization) {
            ProjectProgramScope::validateModeSelection(
                $validator,
                $request->input('program_scope_mode', ProgramScopeMode::Specific->value),
                Organization::class,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
            $submittedMode = ProgramScopeMode::tryFrom((string) $request->input('program_scope_mode', ProgramScopeMode::Specific->value))
                ?? ProgramScopeMode::Specific;
            ScopeSync::validateSubmittedMode(
                $validator,
                $this->actor(),
                $organization?->program_scope_mode ?? ProgramScopeMode::None,
                $submittedMode,
            );
            ScopeSync::validateSubmittedProgramsAreInAdminScope(
                $validator,
                $this->actor(),
                ProjectProgramScope::normalizeIds($request->input('program_ids', [])),
                $organization?->programs()->pluck('programs.id')->all() ?? [],
            );
            $this->validateUniqueNameAndStates($validator, $request, $organization);
            $this->validateContacts($validator, $request);
        });

        $validated = $validator->validate();
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, Organization::class)->value;

        return $validated;
    }

    /**
     * Same name may exist in different states, but not in an overlapping state.
     */
    private function validateUniqueNameAndStates($validator, Request $request, ?Organization $organization): void
    {
        $name = trim((string) $request->input('name', ''));
        $stateIds = collect($request->input('state_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($name === '' || $stateIds === []) {
            return;
        }

        $overlappingStates = State::query()
            ->whereIn('id', $stateIds)
            ->whereHas('organizations', function ($query) use ($name, $organization) {
                $query->whereRaw('LOWER(organizations.name) = LOWER(?)', [$name]);

                if ($organization !== null) {
                    $query->where('organizations.id', '!=', $organization->id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($overlappingStates->isEmpty()) {
            return;
        }

        $message = $this->overlappingOrganizationStateMessage($name, $overlappingStates);

        $validator->errors()->add('name', $message);
        $validator->errors()->add('state_ids', $message);
    }

    private function overlappingOrganizationStateMessage(string $name, Collection $overlappingStates): string
    {
        return sprintf(
            'An organization named %s already exists in %s.',
            $name,
            $overlappingStates->pluck('name')->join(', ')
        );
    }

    private function validateContacts($validator, Request $request): void
    {
        $rows = $request->input('contacts', []);

        if (! is_array($rows)) {
            return;
        }

        $primaryCount = 0;

        foreach ($rows as $key => $row) {
            if (! is_array($row) || filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));

            if ($name === '' && $email === '' && $title === '' && $phone === '') {
                continue;
            }

            if ($name === '' && $email === '') {
                $validator->errors()->add("contacts.{$key}.name", 'Provide a name or an email for each contact.');
            }

            if (filter_var($row['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $primaryCount++;
            }
        }

        if ($primaryCount > 1) {
            $validator->errors()->add('contacts', 'Only one contact can be marked as primary.');
        }
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

    private function syncOrganizationContacts(Organization $organization, array $rows): void
    {
        $existingContacts = $organization->contacts()->get()->keyBy('id');
        $sortOrder = 0;

        // Primary contact is synced first so it lands at sort_order 1 and sorts to the top.
        $orderedRows = collect($rows)
            ->filter(fn ($row) => is_array($row))
            ->sortByDesc(fn ($row) => filter_var($row['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN));

        foreach ($orderedRows as $row) {
            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $markedForDeletion = filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($markedForDeletion) {
                if ($rowId && $existingContacts->has($rowId)) {
                    $contact = $existingContacts->get($rowId);
                    if ($contact instanceof OrganizationContact) {
                        $contact->delete();
                    }
                }

                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $email = trim((string) ($row['email'] ?? ''));
            $title = trim((string) ($row['title'] ?? ''));
            $phone = trim((string) ($row['phone'] ?? ''));

            if ($name === '' && $email === '' && $title === '' && $phone === '') {
                continue;
            }

            $attributes = [
                'name' => $name !== '' ? $name : null,
                'email' => $email !== '' ? $email : null,
                'title' => $title !== '' ? $title : null,
                'phone' => $phone !== '' ? $phone : null,
                'is_primary' => filter_var($row['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => ++$sortOrder,
            ];

            if ($rowId && $existingContacts->has($rowId)) {
                $contact = $existingContacts->get($rowId);
                if ($contact instanceof OrganizationContact) {
                    $contact->update($attributes);
                }
            } else {
                $organization->contacts()->create($attributes);
            }
        }
    }
}

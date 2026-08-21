<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgreementRequest;
use App\Enums\ProgramScopeMode;
use App\Models\Organization;
use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Support\AgreementDeliverableDisplay;
use App\Models\AgreementCertificationCandidate;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use App\Models\KfsAccount;
use App\Models\LoggingField;
use App\Models\Project;
use App\Models\Program;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use App\Services\DeliverableContributionService;
use App\Services\AgreementDuplicationService;
use App\Support\ActivityTypeDuration;
use App\Support\DeliverableHistoryScope;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AgreementController extends Controller
{
    public function __construct(
        private DeliverableContributionService $deliverableContributionService,
        private AgreementDuplicationService $agreementDuplicationService,
    ) {
    }

    public function index(Request $request)
    {
        $states = State::query()->get(['id', 'name'])->sortBy('name')->values();
        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);

        $query = Agreement::query()->with([
            'states',
            'programs.projects:id,name',
            'principalInvestigators:id,name',
        ]);

        if (!Auth::user()->isAdmin()) {
            $query->accessibleBy(Auth::user())
                ->active();
        } elseif ($request->filled('active')) {
            $query->where('agreements.active', $request->input('active') === '1');
        }

        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereIlike('name', "%{$search}%")
                    ->orWhereHas('states', fn ($stateQuery) => $stateQuery->whereIlike('name', "%{$search}%"))
                    ->orWhereHas('programs.projects', fn ($projectQuery) => $projectQuery->whereIlike('name', "%{$search}%"))
                    ->orWhereHas('programs', fn ($programQuery) => $programQuery->whereIlike('name', "%{$search}%"));
            });
        }

        if ($request->filled('state_id')) {
            $query->whereHas('states', fn ($q) => $q->where('states.id', $request->integer('state_id')));
        }

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('programs.projects', fn ($relation) => $relation->where('projects.id', $projectId))
                    ->orWhere('agreements.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhere('agreements.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $this->applyAgreementIndexSort($query, $sort, $direction);

        $agreements = $query->paginate(20)->withQueryString();

        if ($request->header('HX-Request') === 'true' && $request->input('partial') === 'filters') {
            return view('agreements.partials.filters', compact(
                'states',
                'filterProjects',
                'filterPrograms',
                'sort',
                'direction',
            ));
        }

        if ($request->header('HX-Request') === 'true') {
            return view('agreements.partials.table', compact('agreements', 'sort', 'direction'));
        }

        return view('agreements.index', compact(
            'agreements',
            'states',
            'filterProjects',
            'filterPrograms',
            'sort',
            'direction',
        ));
    }

    private function applyAgreementIndexSort($query, string $sort, string $direction): void
    {
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        match ($sort) {
            'start_date' => $query->orderBy('agreements.start_date', $direction),
            'end_date' => $query->orderBy('agreements.end_date', $direction),
            'active' => $query->orderBy('agreements.active', $direction)->orderBy('agreements.name', 'asc'),
            'projects' => $query->orderByRaw($this->minAgreementProjectNameSql()." {$dir}"),
            'programs' => $query->orderByRaw($this->minAgreementProgramNameSql()." {$dir}"),
            'states' => $query->orderByRaw($this->minAgreementStateNameSql()." {$dir}"),
            'principal_investigators' => $query->orderByRaw($this->minAgreementPrincipalInvestigatorNameSql()." {$dir}"),
            default => $query->orderBy('agreements.name', $direction),
        };
    }

    private function minAgreementProjectNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM projects p
            INNER JOIN program_project pp ON pp.project_id = p.id
            INNER JOIN agreement_program ap ON ap.program_id = pp.program_id AND ap.agreement_id = agreements.id
        ), '')";
    }

    private function minAgreementProgramNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM programs p
            INNER JOIN agreement_program ap ON ap.program_id = p.id AND ap.agreement_id = agreements.id
        ), '')";
    }

    private function minAgreementStateNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(s.name)
            FROM states s
            INNER JOIN agreement_state ast ON ast.state_id = s.id AND ast.agreement_id = agreements.id
        ), '')";
    }

    private function minAgreementPrincipalInvestigatorNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(u.name)
            FROM users u
            INNER JOIN agreement_principal_investigator api ON api.user_id = u.id AND api.agreement_id = agreements.id
        ), '')";
    }

    public function create()
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create agreements.');

        return view('agreements.create', $this->agreementFormData());
    }

    public function store(AgreementRequest $request)
    {
        $validated = $request->validated();
        $validated['active'] = $request->boolean('active', true);

        $agreement = DB::transaction(function () use ($validated) {
            $agreement = Agreement::create([
                'name' => $validated['name'],
                'active' => $validated['active'],
                'program_scope_mode' => $validated['program_scope_mode'],
                'abstract' => $validated['abstract'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'extension_start_date' => $validated['extension_start_date'] ?? null,
                'extension_end_date' => $validated['extension_end_date'] ?? null,
                'time_tracking_mode' => $validated['time_tracking_mode'] === 'none' ? null : $validated['time_tracking_mode'],
                'require_payor' => $validated['require_payor'],
                'require_payee' => $validated['require_payee'],
            ]);

            $this->syncAgreementRelations($agreement, $validated);
            $this->softUnassignDeliverableUsersOutsideAgreementMembership($agreement);
            $this->syncAgreementCertificationCandidates($agreement, $validated['certification_candidates'] ?? []);
            $this->syncAgreementDeliverables($agreement, $validated['deliverables'] ?? []);
            $this->deliverableContributionService->syncForAgreement($agreement->fresh());

            return $agreement;
        });

        $this->syncAgreementAttachments($agreement, $request);

        return redirect()
            ->route('agreements.edit', $agreement)
            ->with('success', 'Agreement created. You can now add deliverables below.');
    }

    public function show(Agreement $agreement)
    {
        if (!$agreement->active && !Auth::user()->isAdmin()) {
            abort(403, 'Unauthorized access to this agreement.');
        }

        if (!Auth::user()->isAdmin() && !Auth::user()->hasAccessToAgreement($agreement)) {
            abort(403, 'Unauthorized access to this agreement.');
        }

        $agreement->load([
            'organizations',
            'organizationKfsAccounts',
            'states',
            'programs.projects',
            'users',
            'teams.users',
            'deliverables.contactFamily',
            'deliverables.activityType',
            'deliverables.program',
            'deliverables.users',
            'deliverables.teams',
            'deliverables.contributions.contributor',
            'deliverables.contributions.activityHistory',
            'attachments',
            'certificationCandidates',
            'principalInvestigators',
        ]);

        // Get activities for this agreement
        $activities = $agreement->activities()
            ->with(['activityType.contactFamily', 'user', 'participants'])
            ->orderByRecentDisplay()
            ->get();

        // Recent activities (last 10)
        $recentActivities = $activities->take(10);

        // Programs represented in activities
        $programs = $agreement->activities()
            ->with('programs')
            ->get()
            ->pluck('programs')
            ->flatten()
            ->unique('id')
            ->sortBy('name');

        // Lifetime totals
        $lifetimeTotals = [
            'activities' => $activities->count(),
        ];

        // YTD totals (current year)
        $ytdActivities = $activities->filter(fn($e) => $e->engagement_date->year === now()->year);
        $ytdTotals = [
            'activities' => $ytdActivities->count(),
        ];

        $deliverableGroups = AgreementDeliverableDisplay::buildGroupedProgress($agreement);

        return view('agreements.show', compact(
            'agreement',
            'recentActivities',
            'programs',
            'lifetimeTotals',
            'ytdTotals',
            'deliverableGroups'
        ));
    }

    public function edit(Agreement $agreement)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can edit agreements.');

        return view('agreements.edit', $this->agreementFormData($agreement));
    }

    public function duplicate(Agreement $agreement)
    {
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can duplicate agreements.');

        $copy = $this->agreementDuplicationService->duplicate($agreement);

        return redirect()
            ->route('agreements.edit', $copy)
            ->with('success', 'Agreement duplicated. Review the copy and save any changes.');
    }

    public function update(AgreementRequest $request, Agreement $agreement)
    {
        $validated = $request->validated();
        $validated['active'] = $request->boolean('active');

        DB::transaction(function () use ($agreement, $validated) {
            $agreement->update([
                'name' => $validated['name'],
                'active' => $validated['active'],
                'program_scope_mode' => $validated['program_scope_mode'],
                'abstract' => $validated['abstract'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'extension_start_date' => $validated['extension_start_date'] ?? null,
                'extension_end_date' => $validated['extension_end_date'] ?? null,
                'time_tracking_mode' => $validated['time_tracking_mode'] === 'none' ? null : $validated['time_tracking_mode'],
                'require_payor' => $validated['require_payor'],
                'require_payee' => $validated['require_payee'],
            ]);

            $this->syncAgreementRelations($agreement, $validated);
            $this->softUnassignDeliverableUsersOutsideAgreementMembership($agreement);
            $this->syncAgreementCertificationCandidates($agreement, $validated['certification_candidates'] ?? []);
            $this->syncAgreementDeliverables($agreement, $validated['deliverables'] ?? []);
            $this->deliverableContributionService->syncForAgreement($agreement->fresh());
        });

        $this->syncAgreementAttachments($agreement, $request);

        return redirect()
            ->route('agreements.index')
            ->with('success', 'Agreement updated successfully.');
    }

    private function syncAgreementAttachments(Agreement $agreement, Request $request): void
    {
        $deletedAttachmentIds = collect($request->input('deleted_attachment_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->all();

        if (!empty($deletedAttachmentIds)) {
            $agreement->attachments()
                ->whereIn('id', $deletedAttachmentIds)
                ->get()
                ->each(function ($attachment) {
                    $attachment->delete();
                });
        }

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $filename = $file->getClientOriginalName();
                $path = $file->store('agreement-attachments');

                $agreement->attachments()->create([
                    'filename' => $filename,
                    'file_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }
    }

    private function syncAgreementRelations(Agreement $agreement, array $validated): void
    {
        $selectedProgramIds = ProjectProgramScope::modeAwareProgramIds(
            $validated['program_scope_mode'] ?? ProgramScopeMode::Specific->value,
            Agreement::class,
            $validated['project_ids'] ?? [],
            $validated['program_ids'] ?? []
        );

        $organizationIds = collect($validated['organization_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $payorSourceIds = collect($validated['organization_payor_source_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique();
        $recipientIds = collect($validated['organization_recipient_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique();

        $organizationSync = $organizationIds
            ->mapWithKeys(fn (int $organizationId) => [
                $organizationId => [
                    'payor_source' => $payorSourceIds->contains($organizationId),
                    'recipient' => $recipientIds->contains($organizationId),
                ],
            ])
            ->all();

        $agreementKfsAccounts = $this->resolveKfsAccounts($validated['kfs_numbers'] ?? []);
        $agreementKfsByNumber = $agreementKfsAccounts->keyBy(fn (KfsAccount $account) => strtoupper($account->number));

        $agreement->organizations()->sync($organizationSync);
        $agreement->kfsAccounts()->sync($agreementKfsAccounts->pluck('id')->all());
        $this->syncAgreementOrganizationKfsAssignments(
            $agreement,
            $organizationIds->all(),
            $validated['organization_kfs_numbers'] ?? [],
            $agreementKfsByNumber
        );
        $agreement->states()->sync($validated['state_ids'] ?? []);
        $agreement->programs()->sync($selectedProgramIds);

        $teamIds = array_values(array_unique($validated['team_ids'] ?? []));
        $teamUserIds = collect();

        if (!empty($teamIds)) {
            $teamUserIds = Team::query()
                ->whereKey($teamIds)
                ->with(['users:id'])
                ->get()
                ->flatMap(fn (Team $team) => $team->users->pluck('id'))
                ->unique()
                ->values();
        }

        $directUserIds = collect($validated['user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => !$teamUserIds->contains($id))
            ->unique()
            ->values()
            ->all();

        $agreement->users()->sync($directUserIds);
        $agreement->teams()->sync($teamIds);
        $agreement->principalInvestigators()->sync($validated['principal_investigator_ids'] ?? []);

        $loggingFieldIds = $validated['agreement_logging_field_ids'] ?? [];
        $requiredFieldIds = $validated['required_agreement_logging_field_ids'] ?? [];
        $syncData = [];
        foreach (array_values(array_unique($loggingFieldIds)) as $index => $fieldId) {
            $syncData[$fieldId] = [
                'is_required' => in_array($fieldId, $requiredFieldIds),
                'sort_order' => $index + 1,
            ];
        }
        $agreement->agreementLoggingFields()->sync($syncData);
    }

    /**
     * @param  array<int|string, mixed>  $values
     * @return \Illuminate\Support\Collection<int, KfsAccount>
     */
    private function resolveKfsAccounts(array $values)
    {
        $numbers = collect($values)
            ->filter(fn ($value) => is_string($value) || is_numeric($value))
            ->map(fn ($value) => strtoupper(trim((string) $value)))
            ->filter()
            ->unique()
            ->values();

        if ($numbers->isEmpty()) {
            return collect();
        }

        return $numbers->map(function (string $number) {
            return KfsAccount::query()->firstOrCreate([
                'number' => $number,
            ]);
        })->values();
    }

    /**
     * @param  array<int, int>  $selectedOrganizationIds
     * @param  array<string, mixed>  $organizationKfsNumbers
     * @param  \Illuminate\Support\Collection<string, KfsAccount>  $agreementKfsByNumber
     */
    private function syncAgreementOrganizationKfsAssignments(
        Agreement $agreement,
        array $selectedOrganizationIds,
        array $organizationKfsNumbers,
        $agreementKfsByNumber,
    ): void {
        $rows = [];
        $selectedOrganizationIdSet = collect($selectedOrganizationIds)
            ->map(fn ($id) => (int) $id)
            ->unique();

        foreach ($organizationKfsNumbers as $organizationId => $numbers) {
            $normalizedOrganizationId = (int) $organizationId;

            if (!$selectedOrganizationIdSet->contains($normalizedOrganizationId) || !is_array($numbers)) {
                continue;
            }

            $normalizedNumbers = collect($numbers)
                ->filter(fn ($value) => is_string($value) || is_numeric($value))
                ->map(fn ($value) => strtoupper(trim((string) $value)))
                ->filter()
                ->unique()
                ->values();

            foreach ($normalizedNumbers as $number) {
                $account = $agreementKfsByNumber->get($number);

                if (!$account) {
                    continue;
                }

                $rows[] = [
                    'agreement_id' => $agreement->id,
                    'organization_id' => $normalizedOrganizationId,
                    'kfs_account_id' => $account->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('agreement_organization_kfs_account')
            ->where('agreement_id', $agreement->id)
            ->delete();

        if ($rows !== []) {
            DB::table('agreement_organization_kfs_account')->insert($rows);
        }
    }

    private function softUnassignDeliverableUsersOutsideAgreementMembership(Agreement $agreement): void
    {
        $agreement->loadMissing(['users', 'teams.users', 'deliverables.users']);

        $memberUserIds = $agreement->users
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->merge(
                $agreement->teams->flatMap(
                    fn (Team $team) => $team->users->pluck('id')->map(fn ($id) => (int) $id)
                )
            )
            ->unique();

        foreach ($agreement->deliverables as $deliverable) {
            foreach ($deliverable->users as $user) {
                if ($user->pivot->unassigned_at || $memberUserIds->contains((int) $user->id)) {
                    continue;
                }

                $deliverable->users()->updateExistingPivot($user->id, [
                    'unassigned_at' => now(),
                ]);
            }
        }
    }

    private function syncAgreementDeliverables(Agreement $agreement, array $deliverables): void
    {
        $agreement->loadMissing(['agreementActivityHistories', 'programs:id']);
        $selectedProgramIds = $agreement->programs->pluck('id')->map(fn ($id) => (int) $id)->all();
        $scopedActivityTypes = ActivityType::query()->with('programs:id')->get();

        $existingDeliverables = $agreement->deliverables()
            ->with(['users', 'teams', 'contributions'])
            ->get()
            ->keyBy('id');
        $retainedDeliverableIds = collect();
        $histories = $agreement->agreementActivityHistories;

        foreach ($deliverables as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $markedForDeletion = filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $rowHasContent = $this->deliverableRowHasContent($row);

            if ($markedForDeletion) {
                if ($rowId && $existingDeliverables->has($rowId)) {
                    $deliverable = $existingDeliverables->get($rowId);
                    $this->retireOrDeleteDeliverable($deliverable, $histories);
                }

                continue;
            }

            if (!$rowHasContent) {
                continue;
            }

            $timeBasis = ($row['metric_type'] ?? null) === 'time'
                ? ($row['time_basis'] ?? 'observed')
                : 'observed';
            $allottedTimeUnit = null;

            //determine which ats are in scope then determine the allotted time unit
            if ($timeBasis === 'allotted') {
                $contactFamilyId = !empty($row['contact_family_id']) ? (int) $row['contact_family_id'] : null;
                $activityTypeId = !empty($row['activity_type_id']) ? (int) $row['activity_type_id'] : null;
                $activityTypesInScope = ActivityTypeDuration::filterActivityTypesInScope(
                    $scopedActivityTypes,
                    $contactFamilyId,
                    $activityTypeId,
                    $selectedProgramIds
                );

                $allottedTimeUnit = ActivityTypeDuration::normalizeAllottedTimeUnit(
                    $contactFamilyId,
                    $activityTypeId,
                    $activityTypesInScope,
                    $row['allotted_time_unit'] ?? null
                );
            }

            $data = [
                'activity_type_id' => $row['activity_type_id'] ?? null,
                'contact_family_id' => $row['contact_family_id'] ?? null,
                'program_id' => $row['program_id'] ?? null,
                'metric_type' => $row['metric_type'] ?? null,
                'time_basis' => $timeBasis,
                'allotted_time_unit' => $allottedTimeUnit,
                'contribution_basis' => $row['contribution_basis'] ?? null,
                'user_grouping_mode' => ($row['contribution_basis'] ?? null) === 'user'
                    ? ($row['user_grouping_mode'] ?? null)
                    : null,
                'include_additional_time' => filter_var($row['include_additional_time'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'target_quantity' => $row['target_quantity'] ?? null,
                'suggested_due_date' => $row['suggested_due_date'] ?? null,
                'sort_order' => $row['sort_order'] ?? 0,
                'notes' => $row['notes'] ?? null,
                'retired_at' => null,
            ];

            $isNewDeliverable = !$rowId || !$existingDeliverables->has($rowId);

            if ($rowId && $existingDeliverables->has($rowId)) {
                $deliverable = $existingDeliverables->get($rowId);

                if (DeliverableHistoryScope::hasMatchingHistory($histories, $deliverable)) {
                    $data = array_merge($data, [
                        'contact_family_id' => $deliverable->contact_family_id,
                        'activity_type_id' => $deliverable->activity_type_id,
                        'program_id' => $deliverable->program_id,
                        'metric_type' => $deliverable->metric_type,
                        'time_basis' => $deliverable->time_basis,
                        'allotted_time_unit' => $deliverable->allotted_time_unit,
                        'contribution_basis' => $deliverable->contribution_basis,
                        'user_grouping_mode' => $deliverable->user_grouping_mode,
                        'include_additional_time' => (bool) $deliverable->include_additional_time,
                    ]);
                }

                $deliverable->update($data);
            } else {
                $deliverable = $agreement->deliverables()->create($data);
            }

            $this->syncDeliverableParticipants($deliverable, $row, $isNewDeliverable);
            $retainedDeliverableIds->push($deliverable->id);
        }

        $staleDeliverableIds = $existingDeliverables->keys()->diff($retainedDeliverableIds)->values();
        if ($staleDeliverableIds->isNotEmpty()) {
            $existingDeliverables
                ->only($staleDeliverableIds->all())
                ->each(fn (AgreementDeliverable $deliverable) => $this->retireOrDeleteDeliverable($deliverable, $histories));
        }
    }

    private function syncDeliverableParticipants(AgreementDeliverable $deliverable, array $row, bool $isNewDeliverable): void
    {
        $contributionBasis = $row['contribution_basis'] ?? null;
        $groupingMode = $row['user_grouping_mode'] ?? null;
        $existingUsersById = $deliverable->users->keyBy(fn ($user) => (int) $user->id);
        $existingTeamsById = $deliverable->teams->keyBy(fn ($team) => (int) $team->id);

        if ($contributionBasis !== 'user') {
            foreach ($existingUsersById as $userId => $user) {
                $deliverable->users()->updateExistingPivot($userId, [
                    'unassigned_at' => $user->pivot->unassigned_at ?? now(),
                ]);
            }

            foreach ($existingTeamsById as $teamId => $team) {
                $deliverable->teams()->updateExistingPivot($teamId, [
                    'unassigned_at' => $team->pivot->unassigned_at ?? now(),
                ]);
            }

            return;
        }

        $directUserIds = collect($row['user_ids'] ?? [])
            ->filter(fn ($id) => $id !== null && $id !== '')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $teamIds = $groupingMode === 'joint'
            ? collect($row['team_ids'] ?? [])
                ->filter(fn ($id) => $id !== null && $id !== '')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
            : collect();

        $teamMembersByUser = Team::query()
            ->whereKey($teamIds)
            ->with('users:id')
            ->get()
            ->flatMap(function (Team $team) {
                return $team->users->map(fn ($user) => [
                    'user_id' => (int) $user->id,
                    'team_id' => (int) $team->id,
                ]);
            })
            ->groupBy('user_id');

        $allUserIds = $directUserIds
            ->merge($teamMembersByUser->keys()->map(fn ($id) => (int) $id))
            ->unique()
            ->values();

        foreach ($allUserIds as $userId) {
            $teamMatches = collect($teamMembersByUser->get($userId, []));
            $sourceTeamId = null;

            if (!$directUserIds->contains($userId) && $teamMatches->count() === 1) {
                $sourceTeamId = $teamMatches->first()['team_id'] ?? null;
            }

            $attributes = [
                'assigned_at' => $existingUsersById->get($userId)?->pivot->assigned_at ?? ($isNewDeliverable ? null : now()),
                'unassigned_at' => null,
                'source_team_id' => $sourceTeamId,
            ];

            if ($existingUsersById->has($userId)) {
                $deliverable->users()->updateExistingPivot($userId, $attributes);
            } else {
                $deliverable->users()->attach($userId, $attributes);
            }
        }

        $staleUserIds = $existingUsersById->keys()->diff($allUserIds)->values();
        foreach ($staleUserIds as $userId) {
            $user = $existingUsersById->get((int) $userId);
            $deliverable->users()->updateExistingPivot($userId, [
                'unassigned_at' => $user->pivot->unassigned_at ?? now(),
            ]);
        }

        foreach ($teamIds as $teamId) {
            $attributes = [
                'assigned_at' => $existingTeamsById->get($teamId)?->pivot->assigned_at ?? ($isNewDeliverable ? null : now()),
                'unassigned_at' => null,
            ];

            if ($existingTeamsById->has($teamId)) {
                $deliverable->teams()->updateExistingPivot($teamId, $attributes);
            } else {
                $deliverable->teams()->attach($teamId, $attributes);
            }
        }

        $staleTeamIds = $existingTeamsById->keys()->diff($teamIds)->values();
        foreach ($staleTeamIds as $teamId) {
            $team = $existingTeamsById->get((int) $teamId);
            $deliverable->teams()->updateExistingPivot($teamId, [
                'unassigned_at' => $team->pivot->unassigned_at ?? now(),
            ]);
        }
    }

    private function retireOrDeleteDeliverable(AgreementDeliverable $deliverable, $histories = null): void
    {
        if ($histories === null) {
            $deliverable->loadMissing('agreement.agreementActivityHistories');
            $histories = $deliverable->agreement?->agreementActivityHistories ?? collect();
        }

        if (DeliverableHistoryScope::hasMatchingHistory($histories, $deliverable)) {
            $deliverable->update(['retired_at' => now()]);
            return;
        }

        AgreementDeliverable::query()->whereKey($deliverable->id)->delete();
    }

    private function syncAgreementCertificationCandidates(Agreement $agreement, array $rows): void
    {
        $existingCandidates = $agreement->certificationCandidates()->get()->keyBy('id');

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $markedForDeletion = filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $value = trim((string) ($row['value'] ?? ''));

            if ($markedForDeletion) {
                if ($rowId && $existingCandidates->has($rowId)) {
                    $existingCandidates->get($rowId)->delete();
                }

                continue;
            }

            if ($value === '') {
                continue;
            }

            if ($rowId && $existingCandidates->has($rowId)) {
                $existingCandidates->get($rowId)->update(['name' => $value]);
            } else {
                $agreement->certificationCandidates()->create([
                    'name' => $value,
                    'program_id' => null,
                    'notes' => null,
                ]);
            }
        }
    }

    private function deliverableRowHasContent(array $row): bool
    {
        $fields = [
            $row['activity_type_id'] ?? null,
            $row['contact_family_id'] ?? null,
            $row['program_id'] ?? null,
            $row['metric_type'] ?? null,
            $row['contribution_basis'] ?? null,
            $row['user_grouping_mode'] ?? null,
            $row['target_quantity'] ?? null,
            $row['suggested_due_date'] ?? null,
            $row['notes'] ?? null,
        ];

        foreach ($fields as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return !empty($row['user_ids']) || !empty($row['team_ids']) || array_key_exists('include_additional_time', $row);
    }

    private function agreementFormData(?Agreement $agreement = null): array
    {
        $states = State::query()->get()->sortBy('name')->values();
        $organizations = Organization::query()
            ->active()
            ->with(['states', 'programs.projects'])
            ->get();
        $kfsAccounts = KfsAccount::query()->ordered()->get();

        if ($agreement) {
            $agreement->loadMissing([
                'organizations.states',
                'organizations.programs.projects',
                'kfsAccounts',
                'organizationKfsAccounts',
            ]);
            $organizations = $organizations
                ->merge($agreement->organizations ?? collect())
                ->unique('id');
            $kfsAccounts = $kfsAccounts
                ->merge($agreement->kfsAccounts ?? collect())
                ->unique('id')
                ->sortBy('number')
                ->values();
        }

        $organizations = $organizations->sortBy('name')->values();
        $users = User::query()->active()->with(['programs.projects'])->get()->sortBy('name')->values();
        $contactFamilies = ContactFamily::query()
            ->where('active', true)
            ->with(['programs.projects'])
            ->get()
            ->sortBy(fn ($item) => [$item->sort_order, $item->name])
            ->values();
        $activityTypes = ActivityType::query()
            ->where('active', true)
            ->with(['contactFamily', 'programs.projects'])
            ->get()
            ->sortBy(fn ($item) => [$item->sort_order, $item->name])
            ->values();
        $projects = ProjectProgramScope::activeProjectsWithPrograms()->sortBy('name')->values();
        $agreementLoggingFields = LoggingField::active()
            ->ordered()
            ->where('available_in_agreements', true)
            ->with(['programs.projects'])
            ->get();
        $candidateNameSuggestions = AgreementCertificationCandidate::query()
            ->distinct()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();

        if ($agreement) {
            $agreement->load([
                'users.programs',
                'teams.users',
                'teams.programs.projects',
                'deliverables.activityType.contactFamily',
                'deliverables.program',
                'deliverables.users.programs',
                'deliverables.teams.programs',
                'agreementActivityHistories',
                'organizations.programs.projects',
                'states',
                'attachments',
                'agreementLoggingFields.programs.projects',
                'programs.projects',
                'certificationCandidates',
                'principalInvestigators.programs',
                'kfsAccounts',
                'organizationKfsAccounts',
            ]);
        }

        $teams = Team::query()
            ->where('active', true)
            ->with([
                'users' => fn ($query) => $query->select('users.id', 'users.name', 'users.role'),
                'programs.projects',
            ])
            ->get();

        if ($agreement) {
            $teams = $teams
                ->merge($agreement->teams ?? collect())
                ->unique('id')
                ->sortBy('name')
                ->values();
        }

        return compact(
            'agreement',
            'states',
            'organizations',
            'users',
            'teams',
            'contactFamilies',
            'activityTypes',
            'projects',
            'agreementLoggingFields',
            'candidateNameSuggestions',
            'kfsAccounts'
        );
    }

    public function destroy(Agreement $agreement)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can delete agreements.');

        Agreement::destroy($agreement->id);

        return redirect()
            ->route('agreements.index')
            ->with('success', 'Agreement deleted successfully.');
    }

    /**
     * Download an agreement attachment.
     */
    public function downloadAttachment(Agreement $agreement, $attachmentId)
    {
        $attachment = $agreement->attachments()->findOrFail($attachmentId);

        return Storage::download(
            $attachment->file_path,
            $attachment->original_filename ?? basename($attachment->file_path)
        );
    }

}

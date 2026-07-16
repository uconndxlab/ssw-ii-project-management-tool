<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgreementRequest;
use App\Models\Organization;
use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Support\AgreementDeliverableDisplay;
use App\Models\AgreementCertificationCandidate;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Project;
use App\Models\Program;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use App\Services\DeliverableContributionService;
use App\Services\AgreementDuplicationService;
use App\Support\DeliverableHistoryScope;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class AgreementController extends Controller
{
    public function __construct(
        private DeliverableContributionService $deliverableContributionService,
        private AgreementDuplicationService $agreementDuplicationService,
    ) {
    }

    public function index(Request $request)
    {
        $baseQuery = Agreement::query();

        // Visibility enforcement: non-admins only see assigned agreements
        if (!Auth::user()->isAdmin()) {
            $baseQuery->whereHas('users', function ($q) {
                $q->where('user_id', Auth::id());
            });
        }

        // Build organizations list for cascading filters
        $organizationsQuery = Organization::query();

        if ($request->filled('state_id')) {
            $stateId = $request->integer('state_id');

            $organizationsQuery->whereHas('agreements.states', function ($q) use ($stateId) {
                $q->where('states.id', $stateId);

                if (!Auth::user()->isAdmin()) {
                    // Additional filtering handled by agreement visibility
                }
            });
        }

        $organizations = $organizationsQuery->get(['id', 'name'])->sortBy('name')->values();
        $states = State::query()->get(['id', 'name'])->sortBy('name')->values();

        $query = Agreement::with([
            'organizations',
            'states',
            'projects',
            'programs',
            'teams.users',
            'users',
        ])->withCount([
            'deliverables as active_deliverables_count' => fn ($builder) => $builder->whereNull('retired_at'),
        ]);

        // Visibility enforcement: non-admins only see assigned agreements
        if (!Auth::user()->isAdmin()) {
            $query->whereHas('users', function ($q) {
                $q->where('user_id', Auth::id());
            });
        }

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('organizations', function ($orgQuery) use ($search) {
                        $orgQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('states', function ($stateQuery) use ($search) {
                        $stateQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Cascading filters
        if ($request->filled('state_id')) {
            $query->whereHas('states', function ($q) use ($request) {
                $q->where('states.id', $request->integer('state_id'));
            });
        }

        if ($request->filled('organization_id')) {
            $query->whereHas('organizations', function ($q) use ($request) {
                $q->where('organizations.id', $request->integer('organization_id'));
            });
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'organization':
                $query->join('organizations', 'agreements.organization_id', '=', 'organizations.id')
                    ->select('agreements.*')
                    ->orderBy('organizations.name', $direction);
                break;

            case 'state':
                $query->join('states', 'agreements.state_id', '=', 'states.id')
                    ->select('agreements.*')
                    ->orderBy('states.name', $direction);
                break;

            case 'start_date':
                $query->orderBy('start_date', $direction);
                break;

            case 'team_members':
                $query->withCount('users')->orderBy('users_count', $direction);
                break;

            case 'name':
            default:
                $query->orderBy('name', $direction);
                break;
        }

        $agreements = $query->paginate(20)->withQueryString();

        // HTMX: filters only
        if ($request->header('HX-Request') === 'true' && $request->input('partial') === 'filters') {
            return view('agreements.partials.filters', compact('organizations', 'states', 'sort', 'direction'));
        }

        // HTMX: table only
        if ($request->header('HX-Request') === 'true') {
            return view('agreements.partials.table', compact('agreements', 'sort', 'direction'));
        }

        return view('agreements.index', compact(
            'agreements',
            'organizations',
            'states',
            'sort',
            'direction'
        ));
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
        $projectIds = array_values(array_unique($validated['project_ids'] ?? []));

        $agreement = DB::transaction(function () use ($validated, $projectIds) {
            $agreement = Agreement::create([
                'name' => $validated['name'],
                'project_id' => $projectIds[0] ?? null,
                'abstract' => $validated['abstract'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'extension_start_date' => $validated['extension_start_date'] ?? null,
                'extension_end_date' => $validated['extension_end_date'] ?? null,
                'time_tracking_mode' => $validated['time_tracking_mode'] === 'none' ? null : $validated['time_tracking_mode'],
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
        // Visibility enforcement
        if (!Auth::user()->isAdmin() && !$agreement->users->contains(Auth::id())) {
            abort(403, 'Unauthorized access to this agreement.');
        }

        $agreement->load([
            'organizations',
            'states',
            'projects',
            'programs',
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
            ->orderBy('engagement_date', 'desc')
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
        $projectIds = array_values(array_unique($validated['project_ids'] ?? []));

        DB::transaction(function () use ($agreement, $validated, $projectIds) {
            $agreement->update([
                'name' => $validated['name'],
                'project_id' => $projectIds[0] ?? null,
                'abstract' => $validated['abstract'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'extension_start_date' => $validated['extension_start_date'] ?? null,
                'extension_end_date' => $validated['extension_end_date'] ?? null,
                'time_tracking_mode' => $validated['time_tracking_mode'] === 'none' ? null : $validated['time_tracking_mode'],
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
                $path = $file->store('agreement-attachments', 'public');

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
        $selectedProjectIds = array_values(array_unique($validated['project_ids'] ?? []));
        $selectedProgramIds = array_values(array_unique($validated['program_ids'] ?? []));

        $agreement->organizations()->sync($validated['organization_ids'] ?? []);
        $agreement->states()->sync($validated['state_ids'] ?? []);
        $agreement->projects()->sync($selectedProjectIds);
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
        foreach ($loggingFieldIds as $fieldId) {
            $syncData[$fieldId] = ['is_required' => in_array($fieldId, $requiredFieldIds)];
        }
        $agreement->agreementLoggingFields()->sync($syncData);
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
        $agreement->loadMissing('agreementActivityHistories');

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

            $data = [
                'activity_type_id' => $row['activity_type_id'] ?? null,
                'contact_family_id' => $row['contact_family_id'] ?? null,
                'program_id' => $row['program_id'] ?? null,
                'metric_type' => $row['metric_type'] ?? null,
                'contribution_basis' => $row['contribution_basis'] ?? null,
                'user_grouping_mode' => $row['user_grouping_mode'] ?? null,
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
        $organizations = Organization::query()->with(['states', 'projects', 'programs'])->get()->sortBy('name')->values();
        $users = User::query()->with(['projects', 'programs'])->get()->sortBy('name')->values();
        $contactFamilies = ContactFamily::query()
            ->where('active', true)
            ->with(['projects', 'programs'])
            ->get()
            ->sortBy(fn ($item) => [$item->sort_order, $item->name])
            ->values();
        $activityTypes = ActivityType::query()
            ->where('active', true)
            ->with(['contactFamily', 'projects', 'programs'])
            ->get()
            ->sortBy(fn ($item) => [$item->sort_order, $item->name])
            ->values();
        $projects = ProjectProgramScope::activeProjectsWithPrograms()->sortBy('name')->values();
        $agreementLoggingFields = LoggingField::active()
            ->ordered()
            ->where('available_in_agreements', true)
            ->with(['projects', 'programs'])
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
                'teams.projects',
                'teams.programs',
                'deliverables.activityType.contactFamily',
                'deliverables.program',
                'deliverables.users.programs',
                'deliverables.teams.programs',
                'agreementActivityHistories',
                'organizations.programs',
                'organizations.projects',
                'states',
                'attachments',
                'agreementLoggingFields.programs',
                'agreementLoggingFields.projects',
                'programs',
                'projects',
                'certificationCandidates',
                'principalInvestigators.programs',
            ]);
        }

        $teams = Team::query()
            ->where('active', true)
            ->with([
                'users' => fn ($query) => $query->select('users.id', 'users.name', 'users.role'),
                'projects',
                'programs',
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
            'candidateNameSuggestions'
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

    // HTMX endpoint for user assignment
    public function assignUser(Request $request, Agreement $agreement)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $agreement->users()->attach($validated['user_id']);
        $agreement->load('users');

        return view('agreements.partials.user-list', compact('agreement'));
    }

    public function removeUser(Request $request, Agreement $agreement, User $user)
    {
        $agreement->users()->detach($user->id);
        $agreement->load('users');

        return view('agreements.partials.user-list', compact('agreement'));
    }

    /**
     * Download an agreement attachment.
     */
    public function downloadAttachment(Agreement $agreement, $attachmentId)
    {
        $attachment = $agreement->attachments()->findOrFail($attachmentId);

        return response()->download(
            storage_path('app/public/' . $attachment->file_path),
            $attachment->filename
        );
    }

}

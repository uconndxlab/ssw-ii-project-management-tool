<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\ActivityAgreementFundingSource;
use App\Models\Agreement;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\Program;
use App\Models\State;
use App\Models\User;
use App\Services\ActivityDuplicationService;
use App\Services\DeliverableContributionService;
use App\Support\ActivityFundingSourceTokens;
use App\Support\ActivityTypeDuration;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ActivityController extends Controller
{
    public function __construct(
        private DeliverableContributionService $deliverableContributionService,
        private ActivityDuplicationService $activityDuplicationService,
    ) {
    }

    public function index(Request $request)
    {
        $visibleAgreements = $this->getVisibleAgreements()->load(['organizations', 'states']);

        $visibleAgreementIds = $visibleAgreements->pluck('id');
        $indexAgreementIds = $this->getActivityIndexAgreementIds();

        $query = Activity::query()
            ->with(['agreements.organizations', 'agreements.states', 'user', 'activityType', 'organizations', 'states'])
            ->whereHas('agreements', function ($q) use ($indexAgreementIds) {
                $q->whereIn('agreements.id', $indexAgreementIds);
            });

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('agreements', function ($agreementQuery) use ($search) {
                    $agreementQuery->where('name', 'like', "%{$search}%")
                        ->orWhereHas('organizations', function ($orgQuery) use ($search) {
                            $orgQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('states', function ($stateQuery) use ($search) {
                            $stateQuery->where('name', 'like', "%{$search}%");
                        });
                })
                ->orWhereHas('organizations', function ($orgQuery) use ($search) {
                    $orgQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('states', function ($stateQuery) use ($search) {
                    $stateQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('activityType', function ($typeQuery) use ($search) {
                    $typeQuery->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%");
                });
            });
        }

        // Filters
        $stateId = $request->filled('state_id') ? $request->integer('state_id') : null;
        $organizationId = $request->filled('organization_id') ? $request->integer('organization_id') : null;
        $agreementId = $request->filled('agreement_id') ? $request->integer('agreement_id') : null;
        $activityTypeId = $request->filled('activity_type_id') ? $request->integer('activity_type_id') : null;

        if ($stateId) {
            $query->whereHas('states', function ($q) use ($stateId) {
                $q->where('states.id', $stateId);
            });
        }

        if ($organizationId) {
            $query->whereHas('organizations', function ($q) use ($organizationId) {
                $q->where('organizations.id', $organizationId);
            });
        }

        if ($agreementId) {
            $query->whereHas('agreements', function ($q) use ($agreementId) {
                $q->where('agreements.id', $agreementId);
            });
        }

        if ($activityTypeId) {
            $query->where('activity_type_id', $activityTypeId);
        }

        // Filter option lists for cascading filters
        $visibleStateIds = $visibleAgreements
            ->flatMap(fn ($agreement) => $agreement->states->pluck('id'))
            ->unique()
            ->values();

        $visibleOrganizationIds = $visibleAgreements
            ->flatMap(fn ($agreement) => $agreement->organizations->pluck('id'))
            ->unique()
            ->values();

        $states = State::query()
            ->whereIn('id', $visibleStateIds)
            ->orderBy('name')
            ->get(['id', 'name']);

        $organizationsQuery = Organization::query()
            ->whereIn('id', $visibleOrganizationIds);

        if ($stateId) {
            $organizationsQuery->whereHas('agreements', function ($q) use ($stateId, $visibleAgreementIds) {
                $q->whereHas('states', function ($stateQuery) use ($stateId) {
                    $stateQuery->where('states.id', $stateId);
                })
                    ->whereIn('agreements.id', $visibleAgreementIds);
            });
        }

        $organizations = $organizationsQuery
            ->orderBy('name')
            ->get(['id', 'name']);

        $agreementsQuery = Agreement::query()
            ->with(['organizations', 'states'])
            ->whereIn('id', $visibleAgreementIds);

        if ($stateId) {
            $agreementsQuery->whereHas('states', function ($q) use ($stateId) {
                $q->where('states.id', $stateId);
            });
        }

        if ($organizationId) {
            $agreementsQuery->whereHas('organizations', function ($q) use ($organizationId) {
                $q->where('organizations.id', $organizationId);
            });
        }

        $agreements = $agreementsQuery
            ->orderBy('name')
            ->get();

        $activityTypes = ActivityType::query()
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Sorting
        $sort = $request->input('sort', 'date');
        $direction = $request->input('direction', 'desc') === 'asc' ? 'asc' : 'desc';

        switch ($sort) {
            case 'agreement':
                // Sort by first agreement name via pivot table
                $query->leftJoin('activity_agreement', 'activities.id', '=', 'activity_agreement.activity_id')
                    ->leftJoin('agreements', 'activity_agreement.agreement_id', '=', 'agreements.id')
                    ->select('activities.*')
                    ->groupBy('activities.id')
                    ->orderBy('agreements.name', $direction);
                break;

            case 'activity_type':
                $query->join('activity_types', 'activities.activity_type_id', '=', 'activity_types.id')
                    ->select('activities.*')
                    ->orderBy('activity_types.name', $direction);
                break;

            case 'logged_by':
                $query->join('users', 'activities.user_id', '=', 'users.id')
                    ->select('activities.*')
                    ->orderBy('users.name', $direction);
                break;

            case 'date':
            default:
                $query->orderBy('engagement_date', $direction);
                break;
        }

        $activities = $query->paginate(50)->withQueryString();

        // HTMX: refresh cascading filters and table together
        if ($request->header('HX-Request') === 'true' && $request->input('partial') === 'filters') {
            return view('activities.partials.filters-response', compact(
                'activities',
                'states',
                'organizations',
                'agreements',
                'activityTypes',
                'sort',
                'direction'
            ));
        }

        // HTMX: table only
        if ($request->header('HX-Request') === 'true') {
            return view('activities.partials.table', compact(
                'activities',
                'sort',
                'direction'
            ));
        }

        return view('activities.index', compact(
            'activities',
            'states',
            'organizations',
            'agreements',
            'activityTypes',
            'sort',
            'direction'
        ));
    }

    public function create(Request $request)
    {
        $agreements = $this->getVisibleAgreements()->load('agreementLoggingFields.programs:id');
        $states = State::orderBy('name')->get();
        $organizations = Organization::active()->orderBy('name')->get();
        $organizations->load(['states:id', 'programs.projects:id']);
        $contactFamilies = ContactFamily::where('active', true)
            ->with(['contactFamilyLoggingFields.programs:id', 'activityTypes' => function ($query) {
                $query->where('active', true)
                    ->with('activityTypeLoggingFields.programs:id')
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Pre-load agreement-scoped selections used to constrain the activity form.
        $agreements->load([
            'organizations',
            'organizationKfsAccounts',
            'kfsAccounts',
            'states',
            'deliverables.activityType',
            'users',
            'teams.users',
            'programs.projects',
        ]);

        // Get pre-selected agreement if provided
        $preselectedAgreementId = $request->query('agreement_id');
        $currentContactFamilyId = old('contact_family_id', null);

        return view('activities.create', compact(
            'agreements',
            'states',
            'organizations',
            'contactFamilies',
            'preselectedAgreementId',
            'currentContactFamilyId'
        ));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'agreement_ids' => ['required', 'array', 'min:1'],
            'agreement_ids.*' => ['exists:agreements,id'],
            'state_ids' => ['required', 'array', 'min:1'],
            'state_ids.*' => ['exists:states,id'],
            'organization_ids' => ['required', 'array', 'min:1'],
            'organization_ids.*' => ['exists:organizations,id'],
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['required', 'array', 'min:1'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['distinct', 'exists:users,id'],
            'engagement_date' => ['required', 'date'],
            'contact_family_id' => ['required', 'exists:contact_families,id'],
            'activity_type_id' => ['required', 'exists:activity_types,id'],
            'internal_only' => ['nullable', 'boolean'],
            'cancelled' => ['nullable', 'boolean'],
            'agreement_logging_values' => ['nullable', 'array'],
            'funding_sources' => ['nullable', 'array'],
            'contact_family_logging_values' => ['nullable', 'array'],
            'activity_logging_values' => ['nullable', 'array'],
            'contact_time' => ['nullable', 'array'],
            'contact_time.activity_hours' => ['nullable', 'numeric', 'min:0'],
            'contact_time.prep_hours' => ['nullable', 'numeric', 'min:0'],
            'contact_time.follow_up_hours' => ['nullable', 'numeric', 'min:0'],
            'participant_times' => ['nullable', 'array'],
            'participant_times.*' => ['nullable', 'array'],
            'participant_times.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'participant_times.*.hours' => ['nullable', 'numeric', 'min:0'],
            'participant_times.*.prep_hours' => ['nullable', 'numeric', 'min:0'],
            'participant_times.*.follow_up_hours' => ['nullable', 'numeric', 'min:0'],
            'completion_count' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $validator->after(function ($validator) use ($request) {
            ProjectProgramScope::validateSelection(
                $validator,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
        });

        $baseValidated = $validator->validate();
        $baseValidated['project_ids'] = ProjectProgramScope::normalizeIds($baseValidated['project_ids'] ?? []);
        $baseValidated['program_ids'] = ProjectProgramScope::normalizeIds($baseValidated['program_ids'] ?? []);
        $baseValidated['participant_user_ids'] = collect($baseValidated['participant_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->assertAgreementIdsAreActive($baseValidated['agreement_ids'] ?? []);

        $agreements = $this->resolveSelectedAgreements($baseValidated['agreement_ids'] ?? []);
        $this->validateAgreementProjectProgramSelections($baseValidated, $agreements);
        $baseValidated['program_ids'] = $this->effectiveActivityProgramIds($baseValidated, $agreements);
        $contactFamily = ContactFamily::with('contactFamilyLoggingFields.programs:id')
            ->findOrFail($baseValidated['contact_family_id']);
        $activityType = ActivityType::with('activityTypeLoggingFields.programs:id')->findOrFail($baseValidated['activity_type_id']);

        $this->validateAgreementCoverageSelections($baseValidated, $agreements);
        $this->validateAgreementClassificationSelections($baseValidated, $agreements);
        $this->validateAgreementParticipantSelections($baseValidated, $agreements);

        $this->validateAgreementFundingSources($baseValidated, $agreements);

        $baseValidated['time_tracking'] = $this->normalizeTimeTrackingPayload($baseValidated, $agreements, $contactFamily);
        $validated = array_merge(
            $baseValidated,
            $request->validate(
                $this->dynamicLoggingFieldValidationRules($agreements, $contactFamily, $activityType, $baseValidated['program_ids'] ?? []),
                [],
                $this->dynamicLoggingFieldValidationAttributes($agreements, $contactFamily, $activityType, $baseValidated['program_ids'] ?? [])
            )
        );
        $activity = null;

        DB::transaction(function () use (&$activity, $validated, $agreements, $contactFamily, $activityType) {
            $activity = Activity::create([
                'user_id' => Auth::id(),
                'engagement_date' => $validated['engagement_date'],
                'activity_type_id' => $validated['activity_type_id'],
                'completion_count' => (int) $validated['completion_count'],
                'internal_only' => $validated['internal_only'] ?? false,
                'cancelled' => $validated['cancelled'] ?? false,
            ]);

            $this->syncActivityDurationSnapshot($activity, $activityType, true);

            $this->syncLoggingFieldAnswers($activity, $validated, $agreements, $contactFamily, $activityType);

            $activity->agreements()->sync($validated['agreement_ids'] ?? []);
            $activity->states()->sync($validated['state_ids'] ?? []);
            $activity->organizations()->sync($validated['organization_ids'] ?? []);
            $activity->programs()->sync($validated['program_ids'] ?? []);
            $activity->participants()->sync($validated['participant_user_ids'] ?? []);

            $this->syncAgreementFundingSources($activity, $validated, $agreements);
            $this->syncActivityTimeTracking($activity, $validated['time_tracking']);
            $this->deliverableContributionService->syncForActivity($activity);
        });

        return redirect()
            ->route('activities.index')
            ->with('success', 'Activity logged successfully.');
    }

    public function show(Activity $activity)
    {
        // Authorization: admin, agreement access, or activity owner
        if (!Auth::user()->isAdmin()) {
            $activity->loadMissing('agreements');
            $hasAgreementAccess = $activity->agreements->contains(
                fn (Agreement $agreement) => Auth::user()->hasAccessToAgreement($agreement),
            );
            $isOwner = (int) $activity->user_id === (int) Auth::id();

            if (!$hasAgreementAccess || !$isOwner) {
                abort(403, 'You do not have access to this activity.');
            }
        }

        $activity->load(['agreements.organizations', 'agreements.states', 'organizations', 'states', 'user', 'programs.projects', 'participants', 'participantTimes.user', 'contactTime', 'activityType.contactFamily', 'activityType.activityTypeLoggingFields', 'loggingFieldAnswers.loggingField', 'agreementFundingSources']);
        $activity->load(['agreements.agreementLoggingFields', 'activityType.contactFamily.contactFamilyLoggingFields']);

        return view('activities.show', compact('activity'));
    }

    public function edit(Activity $activity)
    {
        $this->verifyActivityEditAccess($activity);

        $agreements = $this->getVisibleAgreements()->load('agreementLoggingFields.programs:id');
        $states = State::orderBy('name')->get();
        $activity->loadMissing('organizations');
        $organizations = Organization::active()
            ->orderBy('name')
            ->get()
            ->merge($activity->organizations)
            ->unique('id')
            ->sortBy('name')
            ->values();
        $organizations->load(['states:id', 'programs.projects:id']);
        $contactFamilies = ContactFamily::where('active', true)
            ->with(['contactFamilyLoggingFields.programs:id', 'activityTypes' => function ($query) {
                $query->where('active', true)
                    ->with('activityTypeLoggingFields.programs:id')
                    ->orderBy('sort_order')
                    ->orderBy('name');
            }])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $activity->load(['activityType.contactFamily', 'activityType.activityTypeLoggingFields', 'agreements', 'states', 'organizations', 'programs.projects', 'participants', 'participantTimes.user', 'contactTime', 'loggingFieldAnswers', 'agreementFundingSources']);
        $agreements = $agreements
            ->merge($activity->agreements)
            ->unique('id')
            ->sortBy('name')
            ->values();
        $agreements->load([
            'organizations',
            'organizationKfsAccounts',
            'kfsAccounts',
            'states',
            'deliverables.activityType',
            'users',
            'teams.users',
            'programs.projects',
        ]);
        $currentContactFamilyId = old('contact_family_id', $activity->activityType?->contactFamily?->id);

        return view('activities.edit', compact(
            'activity',
            'agreements',
            'states',
            'organizations',
            'contactFamilies',
            'currentContactFamilyId'
        ));
    }

    public function update(Request $request, Activity $activity)
    {
        $this->verifyActivityEditAccess($activity);

        $validator = Validator::make($request->all(), [
            'agreement_ids' => ['required', 'array', 'min:1'],
            'agreement_ids.*' => ['exists:agreements,id'],
            'state_ids' => ['required', 'array', 'min:1'],
            'state_ids.*' => ['exists:states,id'],
            'organization_ids' => ['required', 'array', 'min:1'],
            'organization_ids.*' => ['exists:organizations,id'],
            'project_ids' => ['required', 'array', 'min:1'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['required', 'array', 'min:1'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['distinct', 'exists:users,id'],
            'engagement_date' => ['required', 'date'],
            'contact_family_id' => ['required', 'exists:contact_families,id'],
            'activity_type_id' => ['required', 'exists:activity_types,id'],
            'internal_only' => ['nullable', 'boolean'],
            'cancelled' => ['nullable', 'boolean'],
            'agreement_logging_values' => ['nullable', 'array'],
            'funding_sources' => ['nullable', 'array'],
            'contact_family_logging_values' => ['nullable', 'array'],
            'activity_logging_values' => ['nullable', 'array'],
            'contact_time' => ['nullable', 'array'],
            'contact_time.activity_hours' => ['nullable', 'numeric', 'min:0'],
            'contact_time.prep_hours' => ['nullable', 'numeric', 'min:0'],
            'contact_time.follow_up_hours' => ['nullable', 'numeric', 'min:0'],
            'participant_times' => ['nullable', 'array'],
            'participant_times.*' => ['nullable', 'array'],
            'participant_times.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'participant_times.*.hours' => ['nullable', 'numeric', 'min:0'],
            'participant_times.*.prep_hours' => ['nullable', 'numeric', 'min:0'],
            'participant_times.*.follow_up_hours' => ['nullable', 'numeric', 'min:0'],
            'completion_count' => ['required', 'integer', 'min:1', 'max:999'],
        ]);

        $validator->after(function ($validator) use ($request) {
            ProjectProgramScope::validateSelection(
                $validator,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
        });

        $baseValidated = $validator->validate();
        $baseValidated['project_ids'] = ProjectProgramScope::normalizeIds($baseValidated['project_ids'] ?? []);
        $baseValidated['program_ids'] = ProjectProgramScope::normalizeIds($baseValidated['program_ids'] ?? []);
        $baseValidated['participant_user_ids'] = collect($baseValidated['participant_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $this->assertAgreementIdsAreActive(
            $this->newlySelectedAgreementIds(
                $baseValidated['agreement_ids'] ?? [],
                $activity->agreements->pluck('id')->all(),
            ),
        );

        $agreements = $this->resolveSelectedAgreements($baseValidated['agreement_ids'] ?? []);
        $this->validateAgreementProjectProgramSelections($baseValidated, $agreements);
        $baseValidated['program_ids'] = $this->effectiveActivityProgramIds($baseValidated, $agreements);
        $contactFamily = ContactFamily::with('contactFamilyLoggingFields.programs:id')
            ->findOrFail($baseValidated['contact_family_id']);
        $activityType = ActivityType::with('activityTypeLoggingFields.programs:id')->findOrFail($baseValidated['activity_type_id']);

        $this->validateAgreementCoverageSelections($baseValidated, $agreements, $activity);
        $this->validateAgreementClassificationSelections($baseValidated, $agreements);
        $this->validateAgreementParticipantSelections($baseValidated, $agreements, $activity);
        $this->validateAgreementFundingSources($baseValidated, $agreements);
        $baseValidated['time_tracking'] = $this->normalizeTimeTrackingPayload($baseValidated, $agreements, $contactFamily);
        $validated = array_merge(
            $baseValidated,
            $request->validate(
                $this->dynamicLoggingFieldValidationRules($agreements, $contactFamily, $activityType, $baseValidated['program_ids'] ?? []),
                [],
                $this->dynamicLoggingFieldValidationAttributes($agreements, $contactFamily, $activityType, $baseValidated['program_ids'] ?? [])
            )
        );

        DB::transaction(function () use ($activity, $validated, $agreements, $contactFamily, $activityType) {
            $activityTypeChanged = (int) $activity->activity_type_id !== (int) $validated['activity_type_id'];

            $activity->update([
                'engagement_date' => $validated['engagement_date'],
                'activity_type_id' => $validated['activity_type_id'],
                'completion_count' => (int) $validated['completion_count'],
                'internal_only' => $validated['internal_only'] ?? false,
                'cancelled' => $validated['cancelled'] ?? false,
            ]);

            $this->syncActivityDurationSnapshot($activity, $activityType, $activityTypeChanged);

            $this->syncLoggingFieldAnswers($activity, $validated, $agreements, $contactFamily, $activityType);

            $activity->agreements()->sync($validated['agreement_ids'] ?? []);
            $activity->states()->sync($validated['state_ids'] ?? []);
            $activity->organizations()->sync($validated['organization_ids'] ?? []);
            $activity->programs()->sync($validated['program_ids'] ?? []);
            $activity->participants()->sync($validated['participant_user_ids'] ?? []);

            $this->syncAgreementFundingSources($activity, $validated, $agreements);
            $this->syncActivityTimeTracking($activity, $validated['time_tracking']);
            $this->deliverableContributionService->syncForActivity($activity);
        });

        return redirect()
            ->route('activities.index')
            ->with('success', 'Activity updated successfully.');
    }

    public function duplicate(Activity $activity)
    {
        $this->verifyActivityEditAccess($activity);

        $copy = $this->activityDuplicationService->duplicate($activity, (int) Auth::id());

        return redirect()
            ->route('activities.edit', $copy)
            ->with('success', 'Activity duplicated. Review the copy and save any changes.');
    }

    public function destroy(Activity $activity)
    {
        // Authorization: admin can delete any, staff/consultant can only delete their own
        if (!Auth::user()->isAdmin() && $activity->user_id !== Auth::id()) {
            abort(403, 'You can only delete your own activities.');
        }

        $activity->delete();

        return redirect()
            ->route('activities.index')
            ->with('success', 'Activity deleted successfully.');
    }

    /**
     * Get agreements visible to current user based on role
     */
    private function getVisibleAgreements()
    {
        if (Auth::user()->isAdmin()) {
            return Agreement::query()->active()->with('organizations')->orderBy('name')->get();
        }

        return Auth::user()->accessibleAgreementsQuery()
            ->where('agreements.active', true)
            ->with('organizations')
            ->orderBy('name')
            ->get();
    }

    /**
     * Agreement IDs whose activities appear on the activity index (includes inactive assignments for history).
     */
    private function getActivityIndexAgreementIds()
    {
        if (Auth::user()->isAdmin()) {
            return Agreement::query()->pluck('id');
        }

        return Auth::user()->accessibleAgreementsQuery()->pluck('agreements.id');
    }

    private function assertAgreementIdsAreActive(array $agreementIds, array $allowedInactiveIds = []): void
    {
        if ($agreementIds === []) {
            return;
        }

        $query = Agreement::query()
            ->whereIn('id', $agreementIds)
            ->where('active', false);

        if ($allowedInactiveIds !== []) {
            $query->whereNotIn('id', $allowedInactiveIds);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'agreement_ids' => 'One or more selected agreements are inactive.',
            ]);
        }
    }

    private function newlySelectedAgreementIds(array $selectedIds, array $existingIds): array
    {
        return array_values(array_diff(
            array_map('intval', $selectedIds),
            array_map('intval', $existingIds),
        ));
    }

    /**
     * Verify current user has access to given agreement
     */
    private function verifyAgreementAccess(int $agreementId): void
    {
        if (Auth::user()->isAdmin()) {
            return;
        }

        $hasAccess = Auth::user()->hasAccessToAgreement($agreementId);

        if (!$hasAccess) {
            abort(403, 'You do not have access to this agreement.');
        }
    }

    /**
     * Verify current user can edit this activity
     * Admins can edit any, non-admins can only edit their own
     */
    private function verifyActivityEditAccess(Activity $activity): void
    {
        if (Auth::user()->isAdmin()) {
            return;
        }

        if ($activity->user_id !== Auth::id()) {
            abort(403, 'You can only edit your own activities.');
        }

        $activity->loadMissing('agreements');

        // Also verify they still have access to a linked agreement
        $hasAccess = $activity->agreements->contains(
            fn (Agreement $agreement) => Auth::user()->hasAccessToAgreement($agreement),
        );
        if (!$hasAccess) {
            abort(403, 'You do not have access to this activity.');
        }
    }

    private function resolveSelectedAgreements(array $agreementIds)
    {
        $agreements = collect();

        foreach ($agreementIds as $agreementId) {
            $this->verifyAgreementAccess((int) $agreementId);
        }

        if (!empty($agreementIds)) {
            $agreements = Agreement::with([
                'agreementLoggingFields.programs:id',
                'organizations',
                'users',
                'teams.users',
            ])
                ->whereIn('id', $agreementIds)
                ->get()
                ->sortBy(fn ($agreement) => array_search($agreement->id, $agreementIds))
                ->values();
        }

        return $agreements;
    }

    private function scopedLoggingFields($agreements, ContactFamily $contactFamily, ActivityType $activityType, array $selectedProgramIds): array
    {
        $selectedProgramIds = collect($selectedProgramIds)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $filterFields = function ($fields) use ($selectedProgramIds) {
            return $fields->filter(function (LoggingField $field) use ($selectedProgramIds) {
                return ProjectProgramScope::matchesSelectedPrograms(
                    $field->programs->pluck('id'),
                    $selectedProgramIds,
                    true
                );
            })->values();
        };

        return [
            'agreements' => $agreements->mapWithKeys(function ($agreement) use ($filterFields) {
                return [(string) $agreement->id => $filterFields($agreement->agreementLoggingFields)];
            }),
            'contact_family' => $filterFields($contactFamily->contactFamilyLoggingFields),
            'activity_type' => $filterFields($activityType->activityTypeLoggingFields),
        ];
    }

    private function dynamicLoggingFieldValidationRules($agreements, ContactFamily $contactFamily, ActivityType $activityType, array $selectedProgramIds): array
    {
        $rules = [];
        $scopedFields = $this->scopedLoggingFields($agreements, $contactFamily, $activityType, $selectedProgramIds);

        foreach ($agreements as $agreement) {
            foreach ($scopedFields['agreements'][(string) $agreement->id] ?? collect() as $field) {
                $rules["agreement_logging_values.{$agreement->id}.{$field->id}"] = $this->rulesForField($field->field_type, (bool) $field->pivot->is_required, $field->options_json ?? []);
            }
        }

        foreach ($scopedFields['contact_family'] as $field) {
            $rules["contact_family_logging_values.{$field->id}"] = $this->rulesForField($field->field_type, (bool) $field->pivot->is_required, $field->options_json ?? []);
        }

        foreach ($scopedFields['activity_type'] as $field) {
            $rules["activity_logging_values.{$field->id}"] = $this->rulesForField($field->field_type, (bool) $field->pivot->is_required, $field->options_json ?? []);
        }

        return $rules;
    }

    private function dynamicLoggingFieldValidationAttributes($agreements, ContactFamily $contactFamily, ActivityType $activityType, array $selectedProgramIds): array
    {
        $attributes = [];
        $scopedFields = $this->scopedLoggingFields($agreements, $contactFamily, $activityType, $selectedProgramIds);

        foreach ($agreements as $agreement) {
            foreach ($scopedFields['agreements'][(string) $agreement->id] ?? collect() as $field) {
                $attributes["agreement_logging_values.{$agreement->id}.{$field->id}"] = $agreement->name . ': ' . $field->name;
            }
        }

        foreach ($scopedFields['contact_family'] as $field) {
            $attributes["contact_family_logging_values.{$field->id}"] = $contactFamily->name . ': ' . $field->name;
        }

        foreach ($scopedFields['activity_type'] as $field) {
            $attributes["activity_logging_values.{$field->id}"] = $activityType->name . ': ' . $field->name;
        }

        return $attributes;
    }

    private function validateAgreementCoverageSelections(array $validated, $agreements, ?Activity $activity = null): void
    {
        $selectedStateIds = collect($validated['state_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $selectedOrganizationIds = collect($validated['organization_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();

        $selectedOrganizations = Organization::query()
            ->whereKey($selectedOrganizationIds)
            ->with(['states:id'])
            ->get();

        $stateAllowedOrganizationIds = $selectedOrganizations
            ->filter(fn ($organization) => $organization->states->pluck('id')->intersect($selectedStateIds)->isNotEmpty())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($selectedOrganizationIds->diff($stateAllowedOrganizationIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'organization_ids' => 'Selected organizations must belong to at least one chosen state.',
            ]);
        }

        $agreements->loadMissing(['organizations:id', 'states:id']);

        $allowedOrganizationIds = $agreements
            ->flatMap(fn ($agreement) => $agreement->organizations->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $allowedStateIds = $agreements
            ->flatMap(fn ($agreement) => $agreement->states->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($selectedOrganizationIds->diff($allowedOrganizationIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'organization_ids' => 'Selected organizations must belong to at least one chosen agreement.',
            ]);
        }

        if ($selectedStateIds->diff($allowedStateIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'state_ids' => 'Selected states must belong to at least one chosen agreement.',
            ]);
        }

        if ($selectedOrganizationIds->isNotEmpty()) {
            $previouslySelectedOrganizationIds = collect($activity?->organizations?->pluck('id') ?? [])
                ->map(fn ($id) => (int) $id);

            $inactiveOrganizationIds = Organization::query()
                ->whereKey($selectedOrganizationIds)
                ->where('active', false)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->reject(fn ($id) => $previouslySelectedOrganizationIds->contains($id));

            if ($inactiveOrganizationIds->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'organization_ids' => 'Inactive organizations cannot be newly added to an activity.',
                ]);
            }
        }
    }

    private function validateAgreementClassificationSelections(array $validated, $agreements): void
    {
        if (empty($validated['agreement_ids'])) {
            return;
        }

        $agreements->loadMissing(['deliverables.activityType']);

        $allowedContactFamilyIds = $agreements
            ->flatMap(function ($agreement) {
                return $agreement->deliverables->flatMap(function ($deliverable) {
                    $ids = [];

                    if ($deliverable->contact_family_id) {
                        $ids[] = (int) $deliverable->contact_family_id;
                    }

                    if ($deliverable->activityType?->contact_family_id) {
                        $ids[] = (int) $deliverable->activityType->contact_family_id;
                    }

                    return $ids;
                });
            })
            ->unique()
            ->values();

        if ($allowedContactFamilyIds->isEmpty()) {
            throw ValidationException::withMessages([
                'contact_family_id' => 'Selected agreements do not cover any deliverable contact families.',
            ]);
        }

        $selectedContactFamilyId = (int) $validated['contact_family_id'];
        $selectedActivityTypeId = (int) $validated['activity_type_id'];

        if (!$allowedContactFamilyIds->contains($selectedContactFamilyId)) {
            throw ValidationException::withMessages([
                'contact_family_id' => 'Selected contact family must be covered by at least one chosen agreement deliverable.',
            ]);
        }

        $hasFamilyLevelDeliverable = $agreements
            ->flatMap->deliverables
            ->contains(function ($deliverable) use ($selectedContactFamilyId) {
                return (int) $deliverable->contact_family_id === $selectedContactFamilyId
                    && !$deliverable->activity_type_id;
            });

        if ($hasFamilyLevelDeliverable) {
            $activityTypeAllowed = ActivityType::query()
                ->whereKey($selectedActivityTypeId)
                ->where('contact_family_id', $selectedContactFamilyId)
                ->where('active', true)
                ->exists();

            if (!$activityTypeAllowed) {
                throw ValidationException::withMessages([
                    'activity_type_id' => 'Selected activity type must belong to the chosen contact family.',
                ]);
            }

            return;
        }

        $allowedActivityTypeIds = $agreements
            ->flatMap->deliverables
            ->pluck('activity_type_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $activityTypeAllowed = ActivityType::query()
            ->whereKey($selectedActivityTypeId)
            ->where('contact_family_id', $selectedContactFamilyId)
            ->whereIn('id', $allowedActivityTypeIds)
            ->where('active', true)
            ->exists();

        if (!$activityTypeAllowed) {
            throw ValidationException::withMessages([
                'activity_type_id' => 'Selected activity type must be covered by at least one chosen agreement deliverable.',
            ]);
        }
    }

    private function validateAgreementProjectProgramSelections(array $validated, $agreements): void
    {
        $selectedProjectIds = collect($validated['project_ids'] ?? [])->map(fn ($id) => (int) $id);
        $selectedProgramIds = collect($validated['program_ids'] ?? [])->map(fn ($id) => (int) $id);

        if ($selectedProjectIds->isEmpty() && $selectedProgramIds->isEmpty()) {
            return;
        }

        if (empty($validated['agreement_ids'])) {
            throw ValidationException::withMessages([
                'project_ids' => 'Select at least one agreement before assigning projects or programs.',
            ]);
        }

        $agreements->loadMissing(['programs.projects:id']);

        $allowedProjectIds = $agreements
            ->flatMap(fn ($agreement) => $agreement->projects->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $allowedProgramIds = $agreements
            ->flatMap(fn ($agreement) => $agreement->programs->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($selectedProjectIds->diff($allowedProjectIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'project_ids' => 'Selected projects must belong to at least one chosen agreement.',
            ]);
        }

        if ($selectedProgramIds->diff($allowedProgramIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'program_ids' => 'Selected programs must belong to at least one chosen agreement.',
            ]);
        }
    }

    private function effectiveActivityProgramIds(array $validated, $agreements): array
    {
        $effectiveProgramIds = ProjectProgramScope::effectiveProgramIds(
            $validated['project_ids'] ?? [],
            $validated['program_ids'] ?? []
        );

        if (empty($validated['agreement_ids'])) {
            return $effectiveProgramIds;
        }

        $agreements->loadMissing('programs:id');
        $allowedProgramIds = $agreements
            ->flatMap(fn ($agreement) => $agreement->programs->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique();

        return collect($effectiveProgramIds)
            ->map(fn ($id) => (int) $id)
            ->intersect($allowedProgramIds)
            ->values()
            ->all();
    }

    private function validateAgreementParticipantSelections(array $validated, $agreements, ?Activity $activity = null): void
    {
        $selectedParticipantIds = collect($validated['participant_user_ids'] ?? [])->map(fn ($id) => (int) $id);

        if ($selectedParticipantIds->isEmpty()) {
            return;
        }

        if (empty($validated['agreement_ids'])) {
            throw ValidationException::withMessages([
                'participant_user_ids' => 'Select at least one agreement before assigning participants.',
            ]);
        }

        $agreements->loadMissing(['users:id', 'teams.users:id']);

        $allowedParticipantIds = $agreements
            ->flatMap(function ($agreement) {
                return $agreement->users->pluck('id')
                    ->concat($agreement->teams->flatMap(fn ($team) => $team->users->pluck('id')));
            })
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($activity) {
            $historicalParticipantIds = $activity->participants()
                ->pluck('users.id')
                ->concat($activity->participantTimes()->whereNotNull('user_id')->pluck('user_id'))
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            $allowedParticipantIds = array_values(array_unique(array_merge($allowedParticipantIds, $historicalParticipantIds)));
        } else {
            $historicalParticipantIds = [];
        }

        $newParticipantIds = $selectedParticipantIds->diff($historicalParticipantIds ?? []);

        if ($newParticipantIds->isNotEmpty()) {
            $hasInactiveNewParticipants = User::query()
                ->whereKey($newParticipantIds)
                ->where('active', false)
                ->exists();

            if ($hasInactiveNewParticipants) {
                throw ValidationException::withMessages([
                    'participant_user_ids' => 'Inactive users cannot be added as activity participants.',
                ]);
            }
        }

        if ($selectedParticipantIds->diff($allowedParticipantIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'participant_user_ids' => 'Selected participants must belong to at least one chosen agreement.',
            ]);
        }
    }

    private function normalizeTimeTrackingPayload(array $validated, $agreements, ContactFamily $contactFamily): array
    {
        $timeTrackingModes = $agreements
            ->pluck('time_tracking_mode')
            ->filter()
            ->map(fn ($mode) => $mode->value)
            ->values();

        $requiresContactTime = $timeTrackingModes->contains(fn ($mode) => in_array($mode, ['by_contact', 'by_user'], true));
        $requiresParticipantTime = $timeTrackingModes->contains('by_user');
        $tracksAdditionalTime = (bool) $contactFamily->track_additional_time;

        $contactInput = is_array($validated['contact_time'] ?? null) ? $validated['contact_time'] : [];
        $participantInput = is_array($validated['participant_times'] ?? null) ? $validated['participant_times'] : [];
        $normalizedContactTime = null;
        $normalizedParticipantTimes = [];

        if ($requiresContactTime) {
            $activityHours = $contactInput['activity_hours'] ?? null;

            if ($activityHours === null || $activityHours === '') {
                throw ValidationException::withMessages([
                    'contact_time.activity_hours' => 'Activity Time is required when selected agreements require time tracking.',
                ]);
            }

            if (!is_numeric($activityHours) || (float) $activityHours <= 0) {
                throw ValidationException::withMessages([
                    'contact_time.activity_hours' => 'Activity Time must be greater than 0.',
                ]);
            }

            $normalizedContactTime = [
                'activity_hours' => round((float) $activityHours, 2),
                'prep_hours' => $tracksAdditionalTime ? round((float) ($contactInput['prep_hours'] ?? 0), 2) : 0.0,
                'follow_up_hours' => $tracksAdditionalTime ? round((float) ($contactInput['follow_up_hours'] ?? 0), 2) : 0.0,
            ];

            if ($normalizedContactTime['prep_hours'] < 0 || $normalizedContactTime['follow_up_hours'] < 0) {
                throw ValidationException::withMessages([
                    'contact_time.prep_hours' => 'Prep and Follow Up time cannot be negative.',
                ]);
            }
        }

        if ($requiresParticipantTime) {
            $selectedParticipantIds = collect($validated['participant_user_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

            if ($selectedParticipantIds->isEmpty()) {
                throw ValidationException::withMessages([
                    'participant_user_ids' => 'Select at least one Delivered By user when agreements require participant time tracking.',
                ]);
            }

            $users = $selectedParticipantIds->isEmpty()
                ? collect()
                : \App\Models\User::query()->whereIn('id', $selectedParticipantIds)->get()->keyBy('id');

            foreach ($selectedParticipantIds as $participantId) {
                $row = is_array($participantInput[$participantId] ?? null) ? $participantInput[$participantId] : [];
                $hours = $row['hours'] ?? null;
                $prepHours = $tracksAdditionalTime ? ($row['prep_hours'] ?? 0) : 0;
                $followUpHours = $tracksAdditionalTime ? ($row['follow_up_hours'] ?? 0) : 0;

                if ($hours === null || $hours === '') {
                    throw ValidationException::withMessages([
                        "participant_times.{$participantId}.hours" => 'Each selected Delivered By user needs an Activity Time value.',
                    ]);
                }

                if (!is_numeric($hours) || (float) $hours <= 0) {
                    throw ValidationException::withMessages([
                        "participant_times.{$participantId}.hours" => 'Participant Activity Time must be greater than 0.',
                    ]);
                }

                if (!is_numeric($prepHours) || (float) $prepHours < 0) {
                    throw ValidationException::withMessages([
                        "participant_times.{$participantId}.prep_hours" => 'Participant Prep Time cannot be negative.',
                    ]);
                }

                if (!is_numeric($followUpHours) || (float) $followUpHours < 0) {
                    throw ValidationException::withMessages([
                        "participant_times.{$participantId}.follow_up_hours" => 'Participant Follow Up Time cannot be negative.',
                    ]);
                }

                $normalizedParticipantTimes[] = [
                    'user_id' => $participantId,
                    'participant_name' => $users->get($participantId)?->name,
                    'hours' => round((float) $hours, 2),
                    'prep_hours' => round((float) $prepHours, 2),
                    'follow_up_hours' => round((float) $followUpHours, 2),
                    'notes' => null,
                ];
            }
        }

        return [
            'requires_contact_time' => $requiresContactTime,
            'requires_participant_time' => $requiresParticipantTime,
            'tracks_additional_time' => $tracksAdditionalTime,
            'contact_time' => $normalizedContactTime,
            'participant_times' => $normalizedParticipantTimes,
        ];
    }

    private function syncActivityTimeTracking(Activity $activity, array $timeTracking): void
    {
        if (!($timeTracking['requires_contact_time'] ?? false) || empty($timeTracking['contact_time'])) {
            $activity->contactTime()->delete();
        } else {
            $activity->contactTime()->updateOrCreate([], $timeTracking['contact_time']);
        }

        $activity->participantTimes()->delete();

        if (($timeTracking['requires_participant_time'] ?? false) && !empty($timeTracking['participant_times'])) {
            $activity->participantTimes()->createMany($timeTracking['participant_times']);
        }
    }

    private function syncActivityDurationSnapshot(Activity $activity, ActivityType $activityType, bool $activityTypeChanged): void
    {
        // only updates if activty changed
        if (!$activityTypeChanged) {
            return;
        }

        // updates the allotted times -> overcomplicated ActivityTypeDuration support class...
        $activity->update(ActivityTypeDuration::snapshotFromActivityType($activityType));
    }

    private function validateAgreementFundingSources(array $validated, $agreements): void
    {
        $eligibleSets = ActivityFundingSourceTokens::buildEligibleTokenSets($agreements);

        $fundingInput = $validated['funding_sources'] ?? [];

        foreach ($agreements as $agreement) {
            $agreementId = (int) $agreement->id;
            $eligible = $eligibleSets[$agreementId] ?? [
                ActivityAgreementFundingSource::ROLE_PAYOR => [],
                ActivityAgreementFundingSource::ROLE_PAYEE => [],
            ];

            $enabledRoles = [
                ActivityAgreementFundingSource::ROLE_PAYOR => (bool) $agreement->require_payor,
                ActivityAgreementFundingSource::ROLE_PAYEE => (bool) $agreement->require_payee,
            ];

            foreach ($enabledRoles as $role => $enabled) {
                if (!$enabled) {
                    continue;
                }

                $eligibleTokens = $eligible[$role] ?? [];
                $selectedTokens = collect(data_get($fundingInput, "{$agreementId}.{$role}", []))
                    ->filter(fn ($token) => is_string($token) && $token !== '')
                    ->values();

                if ($selectedTokens->isEmpty()) {
                    continue;
                }

                foreach ($selectedTokens as $index => $token) {
                    $parsed = ActivityFundingSourceTokens::parseToken($token);

                    if (!$parsed || !in_array($token, $eligibleTokens, true)) {
                        throw ValidationException::withMessages([
                            "funding_sources.{$agreementId}.{$role}.{$index}" => 'The selected funding source is not valid for this agreement.',
                        ]);
                    }
                }
            }
        }
    }

    private function syncAgreementFundingSources(Activity $activity, array $validated, $agreements): void
    {
        $activity->agreementFundingSources()->delete();

        $fundingInput = $validated['funding_sources'] ?? [];
        $rows = [];

        foreach ($agreements as $agreement) {
            $agreementId = (int) $agreement->id;

            $roleRequirements = [
                ActivityAgreementFundingSource::ROLE_PAYOR => (bool) $agreement->require_payor,
                ActivityAgreementFundingSource::ROLE_PAYEE => (bool) $agreement->require_payee,
            ];

            foreach ($roleRequirements as $role => $required) {
                if (!$required) {
                    continue;
                }

                $tokens = collect(data_get($fundingInput, "{$agreementId}.{$role}", []))
                    ->filter(fn ($token) => is_string($token) && $token !== '')
                    ->unique()
                    ->values();

                foreach ($tokens as $token) {
                    $parsed = ActivityFundingSourceTokens::parseToken($token);

                    if (!$parsed) {
                        continue;
                    }

                    $snapshot = ActivityFundingSourceTokens::snapshotForSelection($agreement, $role, $parsed);

                    if (is_array($snapshot['kfs_numbers_snapshot'] ?? null)) {
                        $snapshot['kfs_numbers_snapshot'] = json_encode(array_values($snapshot['kfs_numbers_snapshot']));
                    }

                    $rows[] = [
                        'activity_id' => $activity->id,
                        'agreement_id' => $agreementId,
                        'role' => $role,
                        'source_type' => $parsed['source_type'],
                        'source_id' => $parsed['source_id'],
                        ...$snapshot,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }
        }

        if ($rows !== []) {
            ActivityAgreementFundingSource::insert($rows);
        }
    }

    private function rulesForField(string $fieldType, bool $required, array $options = []): array
    {
        $prefix = $required ? ['required'] : ['nullable'];

        return match ($fieldType) {
            'number' => array_merge($prefix, ['integer']),
            'decimal' => array_merge($prefix, ['numeric']),
            'checkbox' => array_merge($prefix, ['boolean']),
            'select' => array_merge($prefix, [Rule::in($options)]),
            'document' => array_merge($required ? ['required'] : ['nullable'], ['file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:'.config('uploads.max_file_kb')]),
            'textarea', 'text' => array_merge($prefix, ['string', 'max:5000']),
            default => array_merge($prefix, ['string', 'max:5000']),
        };
    }

    private function syncLoggingFieldAnswers(Activity $activity, array $validated, $agreements, ContactFamily $contactFamily, ActivityType $activityType): void
    {
        $activity->loadMissing('loggingFieldAnswers');
        $scopedFields = $this->scopedLoggingFields($agreements, $contactFamily, $activityType, $validated['program_ids'] ?? []);

        $existingAgreementValues = $activity->agreement_logging_values;
        $existingContactFamilyValues = $activity->contact_family_logging_values;
        $existingActivityValues = $activity->activity_type_logging_values;
        $payload = [];

        foreach ($agreements as $agreement) {
            foreach ($scopedFields['agreements'][(string) $agreement->id] ?? collect() as $field) {
                $answer = $this->buildLoggingFieldAnswerPayload(
                    $field,
                    'agreement',
                    (int) $agreement->id,
                    data_get($validated, "agreement_logging_values.{$agreement->id}.{$field->id}"),
                    request()->file("agreement_logging_values.{$agreement->id}.{$field->id}"),
                    data_get($existingAgreementValues, "{$agreement->id}.{$field->id}")
                );

                if ($answer !== null) {
                    $payload[] = $answer;
                }
            }
        }

        foreach ($scopedFields['contact_family'] as $field) {
            $answer = $this->buildLoggingFieldAnswerPayload(
                $field,
                'contact_family',
                (int) $contactFamily->id,
                data_get($validated, "contact_family_logging_values.{$field->id}"),
                request()->file("contact_family_logging_values.{$field->id}"),
                data_get($existingContactFamilyValues, (string) $field->id)
            );

            if ($answer !== null) {
                $payload[] = $answer;
            }
        }

        foreach ($scopedFields['activity_type'] as $field) {
            $answer = $this->buildLoggingFieldAnswerPayload(
                $field,
                'activity_type',
                (int) $activityType->id,
                data_get($validated, "activity_logging_values.{$field->id}"),
                request()->file("activity_logging_values.{$field->id}"),
                data_get($existingActivityValues, (string) $field->id)
            );

            if ($answer !== null) {
                $payload[] = $answer;
            }
        }

        $activity->loggingFieldAnswers()->delete();

        if (!empty($payload)) {
            $activity->loggingFieldAnswers()->createMany($payload);
        }
    }

    private function buildLoggingFieldAnswerPayload(LoggingField $field, string $contextType, int $contextId, mixed $rawValue, mixed $uploadedFile = null, mixed $existingValue = null): ?array
    {
        $payload = [
            'logging_field_id' => $field->id,
            'context_type' => $contextType,
            'context_id' => $contextId,
            'value_text' => null,
            'value_number' => null,
            'value_boolean' => null,
            'file_path' => null,
        ];

        if ($field->field_type === 'document') {
            $path = $uploadedFile ? $uploadedFile->store('activity-documents') : $existingValue;

            return $path ? array_merge($payload, ['file_path' => $path]) : null;
        }

        $value = $this->normalizeLoggingFieldValue($field->field_type, $rawValue);

        return match ($field->field_type) {
            'number', 'decimal' => $value === null ? null : array_merge($payload, ['value_number' => $value]),
            'checkbox' => array_merge($payload, ['value_boolean' => $value]),
            default => $value === null ? null : array_merge($payload, ['value_text' => (string) $value]),
        };
    }

    private function normalizeLoggingFieldValue(string $fieldType, mixed $value): mixed
    {
        return match ($fieldType) {
            'number' => $value === null || $value === '' ? null : (int) $value,
            'decimal' => $value === null || $value === '' ? null : (float) $value,
            'checkbox' => (bool) $value,
            default => $value === '' ? null : $value,
        };
    }

    public function downloadLoggingFieldDocument(Activity $activity, string $context, int $fieldId, ?int $agreementId = null): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $contextId = match ($context) {
            'agreement' => $agreementId,
            'activity_type' => $activity->activity_type_id,
            default => $activity->activityType()->value('contact_family_id'),
        };

        $path = $activity->loggingFieldAnswers()
            ->where('logging_field_id', $fieldId)
            ->where('context_type', $context)
            ->where('context_id', $contextId)
            ->value('file_path');

        abort_unless($path && Storage::exists($path), 404);

        return Storage::download($path);
    }
}

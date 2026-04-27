<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\ActivityType;
use App\Models\Organization;
use App\Models\Program;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $visibleAgreements = $this->getVisibleAgreements()->load(['organizations', 'states']);

        $visibleAgreementIds = $visibleAgreements->pluck('id');

        $query = Activity::query()
            ->with(['agreements.organizations', 'agreements.states', 'user', 'activityType', 'organizations', 'states'])
            ->whereHas('agreements', function ($q) use ($visibleAgreementIds) {
                $q->whereIn('agreements.id', $visibleAgreementIds);
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

            case 'total_hours':
                $query->orderByRaw(
                    '(COALESCE(event_hours, 0) + COALESCE(prep_hours, 0) + COALESCE(followup_hours, 0)) ' . $direction
                );
                break;

            case 'date':
            default:
                $query->orderBy('engagement_date', $direction);
                break;
        }

        $activities = $query->paginate(50)->withQueryString();

        // HTMX: filters only
        if ($request->header('HX-Request') === 'true' && $request->input('partial') === 'filters') {
            return view('activities.partials.filters', compact(
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
        $agreements = $this->getVisibleAgreements();
        $states = State::orderBy('name')->get();
        $organizations = Organization::orderBy('name')->get();
        $programs = Program::where('active', true)->orderBy('name')->get();
        $contactFamilies = \App\Models\ContactFamily::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        
        // Pre-load users for each agreement for participant selection
        $agreements->load(['users', 'loggingFields']);
        $contactFamilies->load('loggingFields');
        
        // Get all active logging fields for reference
        $allLoggingFields = \App\Models\LoggingField::active()->ordered()->get();
        
        // Get pre-selected agreement if provided
        $preselectedAgreementId = $request->query('agreement_id');
        $duplicateData = session('duplicate_data', []);
        $currentContactFamilyId = old('contact_family_id', $duplicateData['contact_family_id'] ?? null);
        
        return view('activities.create', compact(
            'agreements',
            'states',
            'organizations',
            'programs',
            'contactFamilies',
            'allLoggingFields',
            'preselectedAgreementId',
            'duplicateData',
            'currentContactFamilyId'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'agreement_ids' => ['nullable', 'array'],
            'agreement_ids.*' => ['exists:agreements,id'],
            'state_ids' => ['nullable', 'array'],
            'state_ids.*' => ['exists:states,id'],
            'organization_ids' => ['nullable', 'array'],
            'organization_ids.*' => ['exists:organizations,id'],
            'engagement_date' => ['required', 'date'],
            'contact_family_id' => ['required', 'exists:contact_families,id'],
            'activity_type_id' => ['required', 'exists:activity_types,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['exists:programs,id'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['exists:users,id'],
            'internal_only' => ['nullable', 'boolean'],
            'time_tracking_mode' => ['required', 'in:engagement,participant'],
            'participant_times' => ['nullable', 'array'],
            'participant_times.*.user_id' => ['exists:users,id'],
            'participant_times.*.hours' => ['numeric', 'min:0.25', 'max:24'],
            'participant_times.*.notes' => ['nullable', 'string', 'max:500'],
            'logging_field_data' => ['nullable', 'array'],
        ]);

        // Verify all selected participants belong to at least one agreement
        if (!empty($validated['participant_user_ids']) && !empty($validated['agreement_ids'])) {
            $this->verifyParticipantsInAgreement($validated['agreement_ids'][0], $validated['participant_user_ids']);
        }

        // Verify participant time users exist if using participant mode
        if ($validated['time_tracking_mode'] === 'participant' && !empty($validated['participant_times'])) {
            $participantTimeUserIds = array_column($validated['participant_times'], 'user_id');
            if (!empty($validated['agreement_ids'])) {
                $this->verifyParticipantsInAgreement($validated['agreement_ids'][0], $participantTimeUserIds);
            }
        }

        $activity = Activity::create([
            'user_id' => Auth::id(),
            'engagement_date' => $validated['engagement_date'],
            'activity_type_id' => $validated['activity_type_id'],
            'internal_only' => $validated['internal_only'] ?? false,
            'time_tracking_mode' => $validated['time_tracking_mode'] ?? 'engagement',
            'logging_field_data' => $validated['logging_field_data'] ?? null,
        ]);

        $activity->agreements()->sync($validated['agreement_ids'] ?? []);
        $activity->states()->sync($validated['state_ids'] ?? []);
        $activity->organizations()->sync($validated['organization_ids'] ?? []);

        if (!empty($validated['program_ids'])) {
            $activity->programs()->sync($validated['program_ids']);
        }

        if (!empty($validated['participant_user_ids'])) {
            $activity->participants()->sync($validated['participant_user_ids']);
        }

        // Save participant times if using participant mode
        if ($activity->time_tracking_mode === 'participant' && !empty($validated['participant_times'])) {
            foreach ($validated['participant_times'] as $participantTime) {
                $activity->participantTimes()->create([
                    'user_id' => $participantTime['user_id'],
                    'hours' => $participantTime['hours'],
                    'notes' => $participantTime['notes'] ?? null,
                ]);
            }
        }

        $saveMode = $request->input('save_mode', 'save');

        if ($saveMode === 'save_new') {
            return redirect()
                ->route('activities.create')
                ->with('success', 'Activity logged. Ready for a new entry.');
        }

        if ($saveMode === 'save_duplicate') {
            $participantTimes = [];
            if ($activity->time_tracking_mode === 'participant') {
                $participantTimes = $activity->participantTimes->map(fn ($pt) => [
                    'user_id' => $pt->user_id,
                    'hours' => $pt->hours,
                    'notes' => $pt->notes,
                ])->toArray();
            }

            $duplicateData = [
                'agreement_ids' => $validated['agreement_ids'] ?? [],
                'state_ids' => $validated['state_ids'] ?? [],
                'organization_ids' => $validated['organization_ids'] ?? [],
                'contact_family_id' => $validated['contact_family_id'] ?? null,
                'activity_type_id' => $validated['activity_type_id'] ?? null,
                'program_ids' => $validated['program_ids'] ?? [],
                'participant_user_ids' => $validated['participant_user_ids'] ?? [],
                'engagement_date' => now()->format('Y-m-d'),
                'internal_only' => $validated['internal_only'] ?? false,
                'time_tracking_mode' => $validated['time_tracking_mode'] ?? 'engagement',
                'participant_times' => $participantTimes,
                'logging_field_data' => $validated['logging_field_data'] ?? null,
            ];

            return redirect()
                ->route('activities.create')
                ->with('success', 'Activity logged. Previous selections loaded for quick duplicate entry.')
                ->with('duplicate_data', $duplicateData);
        }

        return redirect()
            ->route('activities.index')
            ->with('success', 'Activity logged successfully.');
    }

    public function show(Activity $activity)
    {
        // Authorization: admin or assigned to at least one agreement
        if (!Auth::user()->isAdmin()) {
            $agreementIds = $activity->agreements->pluck('id');
            $hasAccess = Auth::user()->agreements()->whereIn('agreements.id', $agreementIds)->exists();
            if (!$hasAccess && $agreementIds->isEmpty()) {
                abort(403, 'You do not have access to this activity.');
            }
        }

        $activity->load(['agreements.organizations', 'agreements.states', 'organizations', 'states', 'user', 'programs', 'participants', 'activityType.contactFamily']);

        return view('activities.show', compact('activity'));
    }

    public function edit(Activity $activity)
    {
        // Authorization: admin can edit any, staff/consultant can only edit their own
        $this->verifyActivityEditAccess($activity);

        $agreements = $this->getVisibleAgreements();
        $states = State::orderBy('name')->get();
        $organizations = Organization::orderBy('name')->get();
        $programs = Program::where('active', true)->orderBy('name')->get();
        $contactFamilies = \App\Models\ContactFamily::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $activityTypes = \App\Models\ActivityType::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $activity->load(['programs', 'participants', 'activityType.contactFamily', 'agreements', 'states', 'organizations', 'participantTimes.user']);
        $currentContactFamilyId = old('contact_family_id', $activity->activityType?->contactFamily?->id);
        
        // Pre-load users for each agreement for participant selection
        $agreements->load('users');

        return view('activities.edit', compact(
            'activity',
            'agreements',
            'states',
            'organizations',
            'programs',
            'contactFamilies',
            'activityTypes',
            'currentContactFamilyId'
        ));
    }

    public function update(Request $request, Activity $activity)
    {
        $this->verifyActivityEditAccess($activity);

        $validated = $request->validate([
            'agreement_ids' => ['nullable', 'array'],
            'agreement_ids.*' => ['exists:agreements,id'],
            'state_ids' => ['nullable', 'array'],
            'state_ids.*' => ['exists:states,id'],
            'organization_ids' => ['nullable', 'array'],
            'organization_ids.*' => ['exists:organizations,id'],
            'engagement_date' => ['required', 'date'],
            'contact_family_id' => ['required', 'exists:contact_families,id'],
            'activity_type_id' => ['required', 'exists:activity_types,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['exists:programs,id'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['exists:users,id'],
            'internal_only' => ['nullable', 'boolean'],
            'time_tracking_mode' => ['required', 'in:engagement,participant'],
            'participant_times' => ['nullable', 'array'],
            'participant_times.*.user_id' => ['exists:users,id'],
            'participant_times.*.hours' => ['numeric', 'min:0.25', 'max:24'],
            'participant_times.*.notes' => ['nullable', 'string', 'max:500'],
            'logging_field_data' => ['nullable', 'array'],
        ]);

        if (!empty($validated['participant_user_ids']) && !empty($validated['agreement_ids'])) {
            $this->verifyParticipantsInAgreement($validated['agreement_ids'][0], $validated['participant_user_ids']);
        }

        // Verify participant time users exist if using participant mode
        if ($validated['time_tracking_mode'] === 'participant' && !empty($validated['participant_times'])) {
            $participantTimeUserIds = array_column($validated['participant_times'], 'user_id');
            if (!empty($validated['agreement_ids'])) {
                $this->verifyParticipantsInAgreement($validated['agreement_ids'][0], $participantTimeUserIds);
            }
        }

        $activity->update([
            'engagement_date' => $validated['engagement_date'],
            'activity_type_id' => $validated['activity_type_id'],
            'internal_only' => $validated['internal_only'] ?? false,
            'time_tracking_mode' => $validated['time_tracking_mode'] ?? 'engagement',
            'logging_field_data' => $validated['logging_field_data'] ?? null,
        ]);

        $activity->agreements()->sync($validated['agreement_ids'] ?? []);
        $activity->states()->sync($validated['state_ids'] ?? []);
        $activity->organizations()->sync($validated['organization_ids'] ?? []);
        $activity->programs()->sync($validated['program_ids'] ?? []);
        $activity->participants()->sync($validated['participant_user_ids'] ?? []);

        // Delete and recreate participant times if using participant mode
        $activity->participantTimes()->delete();
        if ($activity->time_tracking_mode === 'participant' && !empty($validated['participant_times'])) {
            foreach ($validated['participant_times'] as $participantTime) {
                $activity->participantTimes()->create([
                    'user_id' => $participantTime['user_id'],
                    'hours' => $participantTime['hours'],
                    'notes' => $participantTime['notes'] ?? null,
                ]);
            }
        }

        $saveMode = $request->input('save_mode', 'save');

        if ($saveMode === 'save_new') {
            return redirect()
                ->route('activities.create')
                ->with('success', 'Activity updated. Ready for a new entry.');
        }

        if ($saveMode === 'save_duplicate') {
            $participantTimes = [];
            if ($activity->time_tracking_mode === 'participant') {
                $participantTimes = $activity->participantTimes->map(fn ($pt) => [
                    'user_id' => $pt->user_id,
                    'hours' => $pt->hours,
                    'notes' => $pt->notes,
                ])->toArray();
            }

            $duplicateData = [
                'agreement_ids' => $validated['agreement_ids'] ?? [],
                'state_ids' => $validated['state_ids'] ?? [],
                'organization_ids' => $validated['organization_ids'] ?? [],
                'contact_family_id' => $validated['contact_family_id'] ?? null,
                'activity_type_id' => $validated['activity_type_id'] ?? null,
                'program_ids' => $validated['program_ids'] ?? [],
                'participant_user_ids' => $validated['participant_user_ids'] ?? [],
                'engagement_date' => now()->format('Y-m-d'),
                'internal_only' => $validated['internal_only'] ?? false,
                'time_tracking_mode' => $validated['time_tracking_mode'] ?? 'engagement',
                'participant_times' => $participantTimes,
                'logging_field_data' => $validated['logging_field_data'] ?? null,
            ];

            return redirect()
                ->route('activities.create')
                ->with('success', 'Activity updated. Previous selections loaded for quick duplicate entry.')
                ->with('duplicate_data', $duplicateData);
        }

        return redirect()
            ->route('activities.index')
            ->with('success', 'Activity updated successfully.');
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
            return Agreement::with('organizations')->orderBy('name')->get();
        }

        return Auth::user()->agreements()->with('organizations')->orderBy('name')->get();
    }

    /**
     * Verify current user has access to given agreement
     */
    private function verifyAgreementAccess(int $agreementId): void
    {
        if (Auth::user()->isAdmin()) {
            return;
        }

        $hasAccess = Auth::user()->agreements()->where('agreements.id', $agreementId)->exists();

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

        // Also verify they still have access to the agreement
        $agreementIds = $activity->agreements->pluck('id');
        $hasAccess = Auth::user()->agreements()->whereIn('agreements.id', $agreementIds)->exists();
        if (!$hasAccess) {
            abort(403, 'You do not have access to this activity.');
        }
    }

    /**
     * Verify all selected participants belong to the agreement
     */
    private function verifyParticipantsInAgreement(int $agreementId, array $userIds): void
    {
        $agreement = Agreement::findOrFail($agreementId);
        $agreementUserIds = $agreement->users()->pluck('users.id')->toArray();

        foreach ($userIds as $userId) {
            if (!in_array($userId, $agreementUserIds)) {
                abort(422, 'All participants must be members of the agreement.');
            }
        }
    }

    /**
     * HTMX endpoint: Get participant checkboxes for agreement(s)
     */
    public function getParticipantsForAgreement(Request $request)
    {
        $agreementIds = $request->input('agreement_ids', []);
        $selectedIds = $request->input('participant_user_ids', []);
        
        if (empty($agreementIds)) {
            return '<small class="text-muted">Select an agreement first to see team</small>';
        }
        
        // Get unique users from all selected agreements
        $users = \App\Models\User::whereHas('agreements', function ($query) use ($agreementIds) {
            $query->whereIn('agreements.id', $agreementIds);
        })
        ->orderBy('name')
        ->get();
        
        if ($users->isEmpty()) {
            return '<small class="text-muted">No team members assigned to the selected agreement(s)</small>';
        }
        
        return view('activities.partials.participant-checkboxes', [
            'users' => $users,
            'selectedIds' => $selectedIds,
            'pickerId' => 'activity-participants',
        ])->render();
    }

    /**
     * API endpoint to get users for an agreement (for participant time tracking)
     */
    public function getAgreementUsers(Agreement $agreement)
    {
        // Load users for the agreement
        $users = $agreement->users()
            ->select('users.id', 'users.name')
            ->orderBy('users.name')
            ->get();

        return response()->json([
            'users' => $users
        ]);
    }

    /**
     * API endpoint to get users for multiple agreements (for participant time tracking)
     */
    public function getAgreementsUsers(Request $request)
    {
        $agreementIds = $request->input('agreement_ids', []);
        
        if (empty($agreementIds)) {
            return response()->json(['users' => []]);
        }

        // Get unique users from all selected agreements
        $users = \App\Models\User::whereHas('agreements', function ($query) use ($agreementIds) {
            $query->whereIn('agreements.id', $agreementIds);
        })
        ->select('users.id', 'users.name')
        ->orderBy('users.name')
        ->get();

        return response()->json([
            'users' => $users
        ]);
    }

    /**
     * HTMX endpoint to get filtered organizations and states based on selected agreements
     */
    public function getOrganizationsAndStatesForAgreements(Request $request)
    {
        $agreementIds = $request->input('agreement_ids', []);
        $selectedOrganizationIds = $request->input('organization_ids', []);
        $selectedStateIds = $request->input('state_ids', []);
        
        return view('activities.partials.org-state-pickers', [
            'agreementIds' => $agreementIds,
            'selectedOrganizationIds' => $selectedOrganizationIds,
            'selectedStateIds' => $selectedStateIds,
        ])->render();
    }
}

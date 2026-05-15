<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use App\Models\Organization;
use App\Models\Program;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

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
        $agreements = $this->getVisibleAgreements()->load('agreementLoggingFields');
        $states = State::orderBy('name')->get();
        $organizations = Organization::orderBy('name')->get();
        $programs = Program::where('active', true)->orderBy('name')->get();
        $contactFamilies = ContactFamily::where('active', true)
            ->with('contactFamilyLoggingFields')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        
        // Pre-load users for each agreement for participant selection
        $agreements->load('users');
        
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
            'preselectedAgreementId',
            'duplicateData',
            'currentContactFamilyId'
        ));
    }

    public function store(Request $request)
    {
        $baseValidated = $request->validate([
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
            'agreement_logging_values' => ['nullable', 'array'],
            'contact_family_logging_values' => ['nullable', 'array'],
        ]);

        $agreements = $this->resolveSelectedAgreements($baseValidated['agreement_ids'] ?? []);
        $contactFamily = ContactFamily::with('contactFamilyLoggingFields')
            ->findOrFail($baseValidated['contact_family_id']);

        $agreement = $agreements->first();
        $config = $agreement ? $this->getAgreementActivityLoggingConfig($agreement) : [];

        $validated = array_merge(
            $baseValidated,
            $request->validate(array_merge(
                $this->activityLoggingValidationRules($config),
                $this->dynamicLoggingFieldValidationRules($agreements, $contactFamily)
            ))
        );

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
            'logging_field_data' => $this->extractLoggingFieldData($validated, $agreements, $contactFamily),
            'internal_only' => $validated['internal_only'] ?? false,
            'time_tracking_mode' => $validated['time_tracking_mode'] ?? 'engagement',

            'event_hours' => !empty($config['event_hours']) ? ($validated['event_hours'] ?? 0) : 0,
            'prep_hours' => !empty($config['prep_hours']) ? ($validated['prep_hours'] ?? 0) : 0,
            'followup_hours' => !empty($config['followup_hours']) ? ($validated['followup_hours'] ?? 0) : 0,
            'participant_count' => !empty($config['participant_count']) ? ($validated['participant_count'] ?? null) : null,
            'external_attendees' => !empty($config['external_attendees']) ? ($validated['external_attendees'] ?? null) : null,
            'summary' => !empty($config['summary']) ? ($validated['summary'] ?? null) : null,
            'follow_up' => !empty($config['follow_up']) ? ($validated['follow_up'] ?? null) : null,
            'strengths' => !empty($config['strengths']) ? ($validated['strengths'] ?? null) : null,
            'recommendations' => !empty($config['recommendations']) ? ($validated['recommendations'] ?? null) : null,
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
                'event_hours' => null,
                'prep_hours' => 0,
                'followup_hours' => 0,
                'participant_count' => null,
                'external_attendees' => $validated['external_attendees'] ?? null,
                'summary' => $validated['summary'] ?? null,
                'follow_up' => $validated['follow_up'] ?? null,
                'strengths' => $validated['strengths'] ?? null,
                'recommendations' => $validated['recommendations'] ?? null,
                'internal_only' => $validated['internal_only'] ?? false,
                'time_tracking_mode' => $validated['time_tracking_mode'] ?? 'engagement',
                'participant_times' => $participantTimes,
                'agreement_logging_values' => $validated['agreement_logging_values'] ?? [],
                'contact_family_logging_values' => $validated['contact_family_logging_values'] ?? [],
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
        $activity->load(['agreements.agreementLoggingFields', 'activityType.contactFamily.contactFamilyLoggingFields']);

        return view('activities.show', compact('activity'));
    }

    public function edit(Activity $activity)
    {
        // Authorization: admin can edit any, staff/consultant can only edit their own
        $this->verifyActivityEditAccess($activity);

        $agreements = $this->getVisibleAgreements()->load('agreementLoggingFields');
        $states = State::orderBy('name')->get();
        $organizations = Organization::orderBy('name')->get();
        $programs = Program::where('active', true)->orderBy('name')->get();
        $contactFamilies = ContactFamily::where('active', true)
            ->with('contactFamilyLoggingFields')
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

        $baseValidated = $request->validate([
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
            'agreement_logging_values' => ['nullable', 'array'],
            'contact_family_logging_values' => ['nullable', 'array'],
        ]);

        $agreements = $this->resolveSelectedAgreements($baseValidated['agreement_ids'] ?? []);
        $contactFamily = ContactFamily::with('contactFamilyLoggingFields')
            ->findOrFail($baseValidated['contact_family_id']);

        $agreement = $agreements->first();
        $config = $agreement ? $this->getAgreementActivityLoggingConfig($agreement) : [];

        $validated = array_merge(
            $baseValidated,
            $request->validate(array_merge(
                $this->activityLoggingValidationRules($config),
                $this->dynamicLoggingFieldValidationRules($agreements, $contactFamily)
            ))
        );

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
            'logging_field_data' => $this->extractLoggingFieldData($validated, $agreements, $contactFamily),
            'internal_only' => $validated['internal_only'] ?? false,
            'time_tracking_mode' => $validated['time_tracking_mode'] ?? 'engagement',

            'event_hours' => !empty($config['event_hours']) ? ($validated['event_hours'] ?? 0) : 0,
            'prep_hours' => !empty($config['prep_hours']) ? ($validated['prep_hours'] ?? 0) : 0,
            'followup_hours' => !empty($config['followup_hours']) ? ($validated['followup_hours'] ?? 0) : 0,
            'participant_count' => !empty($config['participant_count']) ? ($validated['participant_count'] ?? null) : null,
            'external_attendees' => !empty($config['external_attendees']) ? ($validated['external_attendees'] ?? null) : null,
            'summary' => !empty($config['summary']) ? ($validated['summary'] ?? null) : null,
            'follow_up' => !empty($config['follow_up']) ? ($validated['follow_up'] ?? null) : null,
            'strengths' => !empty($config['strengths']) ? ($validated['strengths'] ?? null) : null,
            'recommendations' => !empty($config['recommendations']) ? ($validated['recommendations'] ?? null) : null,
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
                'event_hours' => null,
                'prep_hours' => 0,
                'followup_hours' => 0,
                'participant_count' => null,
                'external_attendees' => $validated['external_attendees'] ?? null,
                'summary' => $validated['summary'] ?? null,
                'follow_up' => $validated['follow_up'] ?? null,
                'strengths' => $validated['strengths'] ?? null,
                'recommendations' => $validated['recommendations'] ?? null,
                'internal_only' => $validated['internal_only'] ?? false,
                'time_tracking_mode' => $validated['time_tracking_mode'] ?? 'engagement',
                'participant_times' => $participantTimes,
                'agreement_logging_values' => $validated['agreement_logging_values'] ?? [],
                'contact_family_logging_values' => $validated['contact_family_logging_values'] ?? [],
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
     * HTMX endpoint: Get participant checkboxes for an agreement
     */
    public function getParticipantsForAgreement(Request $request)
    {
        $agreementId = $request->input('agreement_id');
        $selectedIds = $request->input('participant_user_ids', []);
        
        if (!$agreementId) {
            return '<small class="text-muted">Select an agreement first to see team</small>';
        }
        
        $agreement = Agreement::with('users')->find($agreementId);
        
        if (!$agreement) {
            return '<small class="text-muted">Agreement not found</small>';
        }
        
        // Verify user has access to this agreement
        if (!Auth::user()->isAdmin()) {
            $hasAccess = Auth::user()->agreements()->where('agreements.id', $agreementId)->exists();
            if (!$hasAccess) {
                return '<small class="text-muted">You do not have access to this agreement</small>';
            }
        }
        
        if ($agreement->users->isEmpty()) {
            return '<small class="text-muted">No team members assigned to this agreement</small>';
        }
        
        return view('activities.partials.participant-checkboxes', [
            'agreement' => $agreement,
            'selectedIds' => $selectedIds,
            'pickerId' => 'activity-participants-' . $agreement->id,
        ])->render();
    }

    private function defaultActivityLoggingConfig(): array
    {
        return [
            'event_hours' => true,
            'prep_hours' => true,
            'followup_hours' => false,
            'participant_count' => true,
            'external_attendees' => true,
            'summary' => true,
            'follow_up' => true,
            'strengths' => false,
            'recommendations' => false,
        ];
    }

    private function getAgreementActivityLoggingConfig(Agreement $agreement): array
    {
        return array_merge(
            $this->defaultActivityLoggingConfig(),
            $agreement->activity_logging_config ?? []
        );
    }

    private function activityLoggingValidationRules(array $config): array
    {
        return [
            'event_hours' => !empty($config['event_hours'])
                ? ['required', 'numeric', 'min:0', 'max:9999.99']
                : ['nullable', 'numeric', 'min:0', 'max:9999.99'],

            'prep_hours' => !empty($config['prep_hours'])
                ? ['nullable', 'numeric', 'min:0', 'max:9999.99']
                : ['nullable', 'numeric', 'min:0', 'max:9999.99'],

            'followup_hours' => !empty($config['followup_hours'])
                ? ['nullable', 'numeric', 'min:0', 'max:9999.99']
                : ['nullable', 'numeric', 'min:0', 'max:9999.99'],

            'participant_count' => !empty($config['participant_count'])
                ? ['nullable', 'integer', 'min:0']
                : ['nullable', 'integer', 'min:0'],

            'external_attendees' => !empty($config['external_attendees'])
                ? ['nullable', 'string', 'max:5000']
                : ['nullable', 'string', 'max:5000'],

            'summary' => !empty($config['summary'])
                ? ['nullable', 'string', 'max:5000']
                : ['nullable', 'string', 'max:5000'],

            'follow_up' => !empty($config['follow_up'])
                ? ['nullable', 'string', 'max:5000']
                : ['nullable', 'string', 'max:5000'],

            'strengths' => !empty($config['strengths'])
                ? ['nullable', 'string', 'max:5000']
                : ['nullable', 'string', 'max:5000'],

            'recommendations' => !empty($config['recommendations'])
                ? ['nullable', 'string', 'max:5000']
                : ['nullable', 'string', 'max:5000'],
        ];
    }

    private function resolveSelectedAgreements(array $agreementIds)
    {
        $agreements = collect();

        foreach ($agreementIds as $agreementId) {
            $this->verifyAgreementAccess((int) $agreementId);
        }

        if (!empty($agreementIds)) {
            $agreements = Agreement::with('agreementLoggingFields')
                ->whereIn('id', $agreementIds)
                ->get()
                ->sortBy(fn ($agreement) => array_search($agreement->id, $agreementIds))
                ->values();
        }

        return $agreements;
    }

    private function dynamicLoggingFieldValidationRules($agreements, ContactFamily $contactFamily): array
    {
        $rules = [];

        foreach ($agreements as $agreement) {
            foreach ($agreement->agreementLoggingFields as $field) {
                $rules["agreement_logging_values.{$agreement->id}.{$field->id}"] = $this->rulesForField($field->field_type, (bool) $field->pivot->is_required, $field->options_json ?? []);
            }
        }

        foreach ($contactFamily->contactFamilyLoggingFields as $field) {
            $rules["contact_family_logging_values.{$field->id}"] = $this->rulesForField($field->field_type, (bool) $field->pivot->is_required, $field->options_json ?? []);
        }

        return $rules;
    }

    private function rulesForField(string $fieldType, bool $required, array $options = []): array
    {
        $prefix = $required ? ['required'] : ['nullable'];

        return match ($fieldType) {
            'number' => array_merge($prefix, ['integer']),
            'decimal' => array_merge($prefix, ['numeric']),
            'checkbox' => array_merge($prefix, ['boolean']),
            'select' => array_merge($prefix, [Rule::in($options)]),
            'textarea', 'text' => array_merge($prefix, ['string', 'max:5000']),
            default => array_merge($prefix, ['string', 'max:5000']),
        };
    }

    private function extractLoggingFieldData(array $validated, $agreements, ContactFamily $contactFamily): array
    {
        $agreementValues = [];
        foreach ($agreements as $agreement) {
            $values = [];
            foreach ($agreement->agreementLoggingFields as $field) {
                $rawValue = data_get($validated, "agreement_logging_values.{$agreement->id}.{$field->id}");
                $values[$field->id] = $this->normalizeLoggingFieldValue($field->field_type, $rawValue);
            }

            if (!empty($values)) {
                $agreementValues[$agreement->id] = $values;
            }
        }

        $contactFamilyValues = [];
        foreach ($contactFamily->contactFamilyLoggingFields as $field) {
            $rawValue = data_get($validated, "contact_family_logging_values.{$field->id}");
            $contactFamilyValues[$field->id] = $this->normalizeLoggingFieldValue($field->field_type, $rawValue);
        }

        return [
            'agreements' => $agreementValues,
            'contact_family' => $contactFamilyValues,
        ];
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
}

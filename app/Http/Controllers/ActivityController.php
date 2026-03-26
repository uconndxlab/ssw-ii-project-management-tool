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
        $visibleAgreements = $this->getVisibleAgreements()->load(['organization', 'state']);

        $visibleAgreementIds = $visibleAgreements->pluck('id');

        $query = Activity::query()
            ->with(['agreement.organization', 'agreement.state', 'user', 'activityType'])
            ->whereIn('agreement_id', $visibleAgreementIds);

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('agreement', function ($agreementQuery) use ($search) {
                    $agreementQuery->where('name', 'like', "%{$search}%")
                        ->orWhereHas('organization', function ($orgQuery) use ($search) {
                            $orgQuery->where('name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('state', function ($stateQuery) use ($search) {
                            $stateQuery->where('name', 'like', "%{$search}%");
                        });
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
            $query->whereHas('agreement', function ($q) use ($stateId) {
                $q->where('state_id', $stateId);
            });
        }

        if ($organizationId) {
            $query->whereHas('agreement', function ($q) use ($organizationId) {
                $q->where('organization_id', $organizationId);
            });
        }

        if ($agreementId) {
            $query->where('agreement_id', $agreementId);
        }

        if ($activityTypeId) {
            $query->where('activity_type_id', $activityTypeId);
        }

        // Filter option lists for cascading filters
        $states = State::query()
            ->whereIn('id', $visibleAgreements->pluck('state_id')->filter()->unique())
            ->orderBy('name')
            ->get(['id', 'name']);

        $organizationsQuery = Organization::query()
            ->whereIn('id', $visibleAgreements->pluck('organization_id')->filter()->unique());

        if ($stateId) {
            $organizationsQuery->whereHas('agreements', function ($q) use ($stateId, $visibleAgreementIds) {
                $q->where('state_id', $stateId)
                    ->whereIn('agreements.id', $visibleAgreementIds);
            });
        }

        $organizations = $organizationsQuery
            ->orderBy('name')
            ->get(['id', 'name']);

        $agreementsQuery = Agreement::query()
            ->with(['organization', 'state'])
            ->whereIn('id', $visibleAgreementIds);

        if ($stateId) {
            $agreementsQuery->where('state_id', $stateId);
        }

        if ($organizationId) {
            $agreementsQuery->where('organization_id', $organizationId);
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
                $query->join('agreements', 'activities.agreement_id', '=', 'agreements.id')
                    ->select('activities.*')
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
        $programs = Program::where('active', true)->orderBy('name')->get();
        $contactFamilies = \App\Models\ContactFamily::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        
        // Pre-load users for each agreement for participant selection
        $agreements->load('users');
        
        // Get pre-selected agreement if provided
        $preselectedAgreementId = $request->query('agreement_id');
        
        return view('activities.create', compact('agreements', 'programs', 'contactFamilies', 'preselectedAgreementId'));
    }

    public function store(Request $request)
    {
        $baseValidated = $request->validate([
            'agreement_id' => ['required', 'exists:agreements,id'],
            'engagement_date' => ['required', 'date'],
            'activity_type_id' => ['required', 'exists:activity_types,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['exists:programs,id'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['exists:users,id'],
        ]);

        $this->verifyAgreementAccess($baseValidated['agreement_id']);

        $agreement = Agreement::findOrFail($baseValidated['agreement_id']);
        $config = $this->getAgreementActivityLoggingConfig($agreement);

        $validated = array_merge(
            $baseValidated,
            $request->validate($this->activityLoggingValidationRules($config))
        );

        // Verify all selected participants belong to the agreement
        if (!empty($validated['participant_user_ids'])) {
            $this->verifyParticipantsInAgreement($validated['agreement_id'], $validated['participant_user_ids']);
        }

        $activity = Activity::create([
            'agreement_id' => $validated['agreement_id'],
            'user_id' => Auth::id(),
            'engagement_date' => $validated['engagement_date'],
            'activity_type_id' => $validated['activity_type_id'],

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

        if (!empty($validated['program_ids'])) {
            $activity->programs()->sync($validated['program_ids']);
        }

        if (!empty($validated['participant_user_ids'])) {
            $activity->participants()->sync($validated['participant_user_ids']);
        }

        return redirect()
            ->route('activities.index')
            ->with('success', 'Activity logged successfully.');
    }

    public function show(Activity $activity)
    {
        // Authorization: admin or assigned to agreement
        if (!Auth::user()->isAdmin()) {
            $hasAccess = Auth::user()->agreements()->where('agreements.id', $activity->agreement_id)->exists();
            if (!$hasAccess) {
                abort(403, 'You do not have access to this activity.');
            }
        }

        $activity->load(['agreement.organization', 'agreement.state', 'user', 'programs', 'participants', 'activityType.contactFamily']);

        return view('activities.show', compact('activity'));
    }

    public function edit(Activity $activity)
    {
        // Authorization: admin can edit any, staff/consultant can only edit their own
        $this->verifyActivityEditAccess($activity);

        $agreements = $this->getVisibleAgreements();
        $programs = Program::where('active', true)->orderBy('name')->get();
        $contactFamilies = \App\Models\ContactFamily::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $activityTypes = \App\Models\ActivityType::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $activity->load(['programs', 'participants', 'activityType.contactFamily']);
        
        // Pre-load users for each agreement for participant selection
        $agreements->load('users');

        return view('activities.edit', compact('activity', 'agreements', 'programs', 'contactFamilies', 'activityTypes'));
    }

    public function update(Request $request, Activity $activity)
    {
        $this->verifyActivityEditAccess($activity);

        $baseValidated = $request->validate([
            'agreement_id' => ['required', 'exists:agreements,id'],
            'engagement_date' => ['required', 'date'],
            'activity_type_id' => ['required', 'exists:activity_types,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['exists:programs,id'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['exists:users,id'],
        ]);

        $this->verifyAgreementAccess($baseValidated['agreement_id']);

        $agreement = Agreement::findOrFail($baseValidated['agreement_id']);
        $config = $this->getAgreementActivityLoggingConfig($agreement);

        $validated = array_merge(
            $baseValidated,
            $request->validate($this->activityLoggingValidationRules($config))
        );

        if (!empty($validated['participant_user_ids'])) {
            $this->verifyParticipantsInAgreement($validated['agreement_id'], $validated['participant_user_ids']);
        }

        $activity->update([
            'agreement_id' => $validated['agreement_id'],
            'engagement_date' => $validated['engagement_date'],
            'activity_type_id' => $validated['activity_type_id'],

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

        $activity->programs()->sync($validated['program_ids'] ?? []);
        $activity->participants()->sync($validated['participant_user_ids'] ?? []);

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
            return Agreement::with('organization')->orderBy('name')->get();
        }

        return Auth::user()->agreements()->with('organization')->orderBy('name')->get();
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
        $hasAccess = Auth::user()->agreements()->where('agreements.id', $activity->agreement_id)->exists();
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
}

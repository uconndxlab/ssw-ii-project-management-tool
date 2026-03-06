<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\Program;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        $query = Activity::query()
            ->with(['agreement.organization', 'agreement.state', 'user', 'activityType']);

        if (!Auth::user()->isAdmin()) {
            $agreementIds = Auth::user()->agreements()->pluck('agreements.id');
            $query->whereIn('agreement_id', $agreementIds);
        }

        $filters = $request->validate([
            'agreement_id' => ['nullable', 'integer', 'exists:agreements,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'activity_type_id' => ['nullable', 'integer', 'exists:activity_types,id'],
        ]);

        if (!empty($filters['agreement_id'])) {
            $query->where('agreement_id', $filters['agreement_id']);
        }

        if (!empty($filters['organization_id'])) {
            $query->whereHas('agreement', function ($q) use ($filters) {
                $q->where('organization_id', $filters['organization_id']);
            });
        }

        if (!empty($filters['state_id'])) {
            $query->whereHas('agreement', function ($q) use ($filters) {
                $q->where('state_id', $filters['state_id']);
            });
        }

        if (!empty($filters['activity_type_id'])) {
            $query->where('activity_type_id', $filters['activity_type_id']);
        }

        $agreements = $this->getVisibleAgreements()->load(['organization', 'state']);

        $orgIds = $agreements->pluck('organization_id')->filter()->unique()->values();
        $organizations = \App\Models\Organization::whereIn('id', $orgIds)->orderBy('name')->get();

        $stateIds = $agreements->pluck('state_id')->filter()->unique()->values();
        $states = State::whereIn('id', $stateIds)->orderBy('name')->get();

        $activityTypes = \App\Models\ActivityType::where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $activities = $query
            ->orderBy('engagement_date', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('activities.index', compact(
            'activities',
            'agreements',
            'organizations',
            'states',
            'activityTypes',
            'filters'
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
        $validated = $request->validate([
            'agreement_id' => ['required', 'exists:agreements,id'],
            'engagement_date' => ['required', 'date'],
            'activity_type_id' => ['required', 'exists:activity_types,id'],
            'event_hours' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'prep_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'followup_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'participant_count' => ['nullable', 'integer', 'min:0'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'follow_up' => ['nullable', 'string', 'max:5000'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['exists:programs,id'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['exists:users,id'],
        ]);

        // Verify user has access to this agreement
        $this->verifyAgreementAccess($validated['agreement_id']);

        // Verify all selected participants belong to the agreement
        if (!empty($validated['participant_user_ids'])) {
            $this->verifyParticipantsInAgreement($validated['agreement_id'], $validated['participant_user_ids']);
        }

        $activity = Activity::create([
            'agreement_id' => $validated['agreement_id'],
            'user_id' => Auth::id(),
            'engagement_date' => $validated['engagement_date'],
            'activity_type_id' => $validated['activity_type_id'],
            'event_hours' => $validated['event_hours'],
            'prep_hours' => $validated['prep_hours'] ?? 0,
            'followup_hours' => $validated['followup_hours'] ?? 0,
            'participant_count' => $validated['participant_count'],
            'summary' => $validated['summary'],
            'follow_up' => $validated['follow_up'],
            'strengths' => $validated['strengths'],
            'recommendations' => $validated['recommendations'],
        ]);

        // Sync programs
        if (!empty($validated['program_ids'])) {
            $activity->programs()->sync($validated['program_ids']);
        }

        // Sync participants
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
        // Authorization: admin can edit any, staff/consultant can only edit their own
        $this->verifyActivityEditAccess($activity);

        $validated = $request->validate([
            'agreement_id' => ['required', 'exists:agreements,id'],
            'engagement_date' => ['required', 'date'],
            'activity_type_id' => ['required', 'exists:activity_types,id'],
            'event_hours' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'prep_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'followup_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'participant_count' => ['nullable', 'integer', 'min:0'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'follow_up' => ['nullable', 'string', 'max:5000'],
            'strengths' => ['nullable', 'string', 'max:5000'],
            'recommendations' => ['nullable', 'string', 'max:5000'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['exists:programs,id'],
            'participant_user_ids' => ['nullable', 'array'],
            'participant_user_ids.*' => ['exists:users,id'],
        ]);

        // Verify user has access to new agreement (in case they changed it)
        $this->verifyAgreementAccess($validated['agreement_id']);

        // Verify all selected participants belong to the agreement
        if (!empty($validated['participant_user_ids'])) {
            $this->verifyParticipantsInAgreement($validated['agreement_id'], $validated['participant_user_ids']);
        }

        $activity->update([
            'agreement_id' => $validated['agreement_id'],
            'engagement_date' => $validated['engagement_date'],
            'activity_type_id' => $validated['activity_type_id'],
            'event_hours' => $validated['event_hours'],
            'prep_hours' => $validated['prep_hours'] ?? 0,
            'followup_hours' => $validated['followup_hours'] ?? 0,
            'participant_count' => $validated['participant_count'],
            'summary' => $validated['summary'],
            'follow_up' => $validated['follow_up'],
            'strengths' => $validated['strengths'],
            'recommendations' => $validated['recommendations'],
        ]);

        // Sync programs
        $activity->programs()->sync($validated['program_ids'] ?? []);

        // Sync participants
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
            return '<small class="text-muted">Select an agreement first to see team members</small>';
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
            'users' => $agreement->users,
            'selectedIds' => $selectedIds
        ])->render();
    }
}

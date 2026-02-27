<?php

namespace App\Http\Controllers;

use App\Models\Engagement;
use App\Models\Project;
use App\Models\Program;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EngagementController extends Controller
{
    public function index(Request $request)
    {
        $query = Engagement::query()
            ->with(['project.organization', 'project.state', 'user']);

        if (!Auth::user()->isAdmin()) {
            $projectIds = Auth::user()->projects()->pluck('projects.id');
            $query->whereIn('project_id', $projectIds);
        }

        $filters = $request->validate([
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'state_id' => ['nullable', 'integer', 'exists:states,id'],
            'activity_type' => ['nullable', 'string', 'in:' . implode(',', Engagement::ACTIVITY_TYPES)],
        ]);

        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (!empty($filters['organization_id'])) {
            $query->whereHas('project', function ($q) use ($filters) {
                $q->where('organization_id', $filters['organization_id']);
            });
        }

        if (!empty($filters['state_id'])) {
            $query->whereHas('project', function ($q) use ($filters) {
                $q->where('state_id', $filters['state_id']);
            });
        }

        if (!empty($filters['activity_type'])) {
            $query->where('activity_type', $filters['activity_type']);
        }

        $projects = $this->getVisibleProjects()->load(['organization', 'state']);

        $orgIds = $projects->pluck('organization_id')->filter()->unique()->values();
        $organizations = \App\Models\Organization::whereIn('id', $orgIds)->orderBy('name')->get();

        $stateIds = $projects->pluck('state_id')->filter()->unique()->values();
        $states = State::whereIn('id', $stateIds)->orderBy('name')->get();

        $engagements = $query
            ->orderBy('engagement_date', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('engagements.index', compact(
            'engagements',
            'projects',
            'organizations',
            'states',
            'filters'
        ));
    }

    public function create()
    {
        $projects = $this->getVisibleProjects();
        $programs = Program::where('active', true)->orderBy('name')->get();
        
        // Pre-load users for each project for participant selection
        $projects->load('users');
        
        return view('engagements.create', compact('projects', 'programs'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'engagement_date' => ['required', 'date'],
            'activity_type' => ['required', 'in:' . implode(',', Engagement::ACTIVITY_TYPES)],
            'deliverable_bucket' => ['required', 'in:' . implode(',', Engagement::DELIVERABLE_BUCKETS)],
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

        // Verify user has access to this project
        $this->verifyProjectAccess($validated['project_id']);

        // Verify all selected participants belong to the project
        if (!empty($validated['participant_user_ids'])) {
            $this->verifyParticipantsInProject($validated['project_id'], $validated['participant_user_ids']);
        }

        $engagement = Engagement::create([
            'project_id' => $validated['project_id'],
            'user_id' => Auth::id(),
            'engagement_date' => $validated['engagement_date'],
            'activity_type' => $validated['activity_type'],
            'deliverable_bucket' => $validated['deliverable_bucket'],
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
            $engagement->programs()->sync($validated['program_ids']);
        }

        // Sync participants
        if (!empty($validated['participant_user_ids'])) {
            $engagement->participants()->sync($validated['participant_user_ids']);
        }

        return redirect()
            ->route('engagements.index')
            ->with('success', 'Engagement logged successfully.');
    }

    public function show(Engagement $engagement)
    {
        // Authorization: admin or assigned to project
        if (!Auth::user()->isAdmin()) {
            $hasAccess = Auth::user()->projects()->where('projects.id', $engagement->project_id)->exists();
            if (!$hasAccess) {
                abort(403, 'You do not have access to this engagement.');
            }
        }

        $engagement->load(['project.organization', 'project.state', 'user', 'programs', 'participants']);

        return view('engagements.show', compact('engagement'));
    }

    public function edit(Engagement $engagement)
    {
        // Authorization: admin can edit any, staff/consultant can only edit their own
        $this->verifyEngagementEditAccess($engagement);

        $projects = $this->getVisibleProjects();
        $programs = Program::where('active', true)->orderBy('name')->get();
        $engagement->load(['programs', 'participants']);
        
        // Pre-load users for each project for participant selection
        $projects->load('users');

        return view('engagements.edit', compact('engagement', 'projects', 'programs'));
    }

    public function update(Request $request, Engagement $engagement)
    {
        // Authorization: admin can edit any, staff/consultant can only edit their own
        $this->verifyEngagementEditAccess($engagement);

        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'engagement_date' => ['required', 'date'],
            'activity_type' => ['required', 'in:' . implode(',', Engagement::ACTIVITY_TYPES)],
            'deliverable_bucket' => ['required', 'in:' . implode(',', Engagement::DELIVERABLE_BUCKETS)],
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

        // Verify user has access to new project (in case they changed it)
        $this->verifyProjectAccess($validated['project_id']);

        // Verify all selected participants belong to the project
        if (!empty($validated['participant_user_ids'])) {
            $this->verifyParticipantsInProject($validated['project_id'], $validated['participant_user_ids']);
        }

        $engagement->update([
            'project_id' => $validated['project_id'],
            'engagement_date' => $validated['engagement_date'],
            'activity_type' => $validated['activity_type'],
            'deliverable_bucket' => $validated['deliverable_bucket'],
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
        $engagement->programs()->sync($validated['program_ids'] ?? []);

        // Sync participants
        $engagement->participants()->sync($validated['participant_user_ids'] ?? []);

        return redirect()
            ->route('engagements.index')
            ->with('success', 'Engagement updated successfully.');
    }

    public function destroy(Engagement $engagement)
    {
        // Authorization: admin can delete any, staff/consultant can only delete their own
        if (!Auth::user()->isAdmin() && $engagement->user_id !== Auth::id()) {
            abort(403, 'You can only delete your own engagements.');
        }

        $engagement->delete();

        return redirect()
            ->route('engagements.index')
            ->with('success', 'Engagement deleted successfully.');
    }

    /**
     * Get projects visible to current user based on role
     */
    private function getVisibleProjects()
    {
        if (Auth::user()->isAdmin()) {
            return Project::with('organization')->orderBy('name')->get();
        }

        return Auth::user()->projects()->with('organization')->orderBy('name')->get();
    }

    /**
     * Verify current user has access to given project
     */
    private function verifyProjectAccess(int $projectId): void
    {
        if (Auth::user()->isAdmin()) {
            return;
        }

        $hasAccess = Auth::user()->projects()->where('projects.id', $projectId)->exists();

        if (!$hasAccess) {
            abort(403, 'You do not have access to this project.');
        }
    }

    /**
     * Verify current user can edit this engagement
     * Admins can edit any, non-admins can only edit their own
     */
    private function verifyEngagementEditAccess(Engagement $engagement): void
    {
        if (Auth::user()->isAdmin()) {
            return;
        }

        if ($engagement->user_id !== Auth::id()) {
            abort(403, 'You can only edit your own engagements.');
        }

        // Also verify they still have access to the project
        $hasAccess = Auth::user()->projects()->where('projects.id', $engagement->project_id)->exists();
        if (!$hasAccess) {
            abort(403, 'You do not have access to this engagement.');
        }
    }

    /**
     * Verify all selected participants belong to the project
     */
    private function verifyParticipantsInProject(int $projectId, array $userIds): void
    {
        $project = Project::findOrFail($projectId);
        $projectUserIds = $project->users()->pluck('users.id')->toArray();

        foreach ($userIds as $userId) {
            if (!in_array($userId, $projectUserIds)) {
                abort(422, 'All participants must be members of the project.');
            }
        }
    }
}

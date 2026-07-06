<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgreementRequest;
use App\Models\Organization;
use App\Models\Agreement;
use App\Models\AgreementAttachment;
use App\Models\AgreementDeliverable;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Project;
use App\Models\Program;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AgreementController extends Controller
{
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

        $query = Agreement::with(['organizations', 'states', 'users']);

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

        $agreement = Agreement::create([
            'name' => $validated['name'],
            'project_id' => $validated['project_id'] ?? null,
            'abstract' => $validated['abstract'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'original_end_date' => $validated['original_end_date'] ?? null,
            'extended_end_date' => $validated['extended_end_date'] ?? null,
            'certification_candidates' => $validated['certification_candidates'] ?? null,
        ]);

        $selectedProgramIds = $validated['program_ids'] ?? [];
        if (!empty($validated['project_id'])) {
            $projectProgramIds = Project::query()->whereKey($validated['project_id'])->first()
                ?->programs()
                ->where('active', true)
                ->pluck('id')
                ->toArray() ?? [];

            $selectedProgramIds = array_values(array_unique(array_merge($selectedProgramIds, $projectProgramIds)));
        }

        $agreement->organizations()->sync($validated['organization_ids'] ?? []);
        $agreement->states()->sync($validated['state_ids'] ?? []);
        $agreement->programs()->sync($selectedProgramIds);
        $agreement->users()->sync($validated['user_ids'] ?? []);
        $agreement->teams()->sync($validated['team_ids'] ?? []);
        
        // Sync logging fields with is_required pivot data
        $loggingFieldIds = $validated['agreement_logging_field_ids'] ?? [];
        $requiredFieldIds = $validated['required_agreement_logging_field_ids'] ?? [];
        $syncData = [];
        foreach ($loggingFieldIds as $fieldId) {
            $syncData[$fieldId] = ['is_required' => in_array($fieldId, $requiredFieldIds)];
        }
        $agreement->agreementLoggingFields()->sync($syncData);
        
        // Handle file uploads
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

        $agreement->load(['organizations', 'states', 'users', 'teams.users', 'deliverables.activityType.contactFamily', 'deliverables.assignedUsers', 'attachments']);
        
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
        
        // Calculate deliverable progress (all activities lifetime)
        $deliverableProgress = $agreement->deliverables->map(function ($deliverable) use ($activities) {
            $matchingActivities = $activities->filter(function ($activity) use ($deliverable) {
                $matches = true;
                
                // Must match activity type if specified
                if ($deliverable->activity_type_id) {
                    $matches = $matches && ($activity->activity_type_id === $deliverable->activity_type_id);
                }
                
                // Must also match contact family if specified
                if ($deliverable->contact_family_id) {
                    $matches = $matches && ($activity->activityType?->contact_family_id === $deliverable->contact_family_id);
                }
                
                // If neither specified, don't match anything
                if (!$deliverable->activity_type_id && !$deliverable->contact_family_id) {
                    return false;
                }
                
                return $matches;
            });
            
            return [
                'deliverable' => $deliverable,
                'completed_activities' => $matchingActivities->count(),
                'completed_hours' => $matchingActivities->sum(fn ($a) => ($a->event_hours ?? 0) + ($a->prep_hours ?? 0) + ($a->followup_hours ?? 0)),
            ];
        });
        
        return view('agreements.show', compact('agreement', 'recentActivities', 'programs', 'lifetimeTotals', 'ytdTotals', 'deliverableProgress'));
    }

    public function edit(Agreement $agreement)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can edit agreements.');

        return view('agreements.edit', $this->agreementFormData($agreement));
    }

    public function update(AgreementRequest $request, Agreement $agreement)
    {
        $validated = $request->validated();

        $agreement->update([
            'name' => $validated['name'],
            'project_id' => $validated['project_id'] ?? null,
            'abstract' => $validated['abstract'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'original_end_date' => $validated['original_end_date'] ?? null,
            'extended_end_date' => $validated['extended_end_date'] ?? null,
            'certification_candidates' => $validated['certification_candidates'] ?? null,
        ]);

        $selectedProgramIds = $validated['program_ids'] ?? [];
        if (!empty($validated['project_id'])) {
            $projectProgramIds = Project::query()->whereKey($validated['project_id'])->first()
                ?->programs()
                ->where('active', true)
                ->pluck('id')
                ->toArray() ?? [];

            $selectedProgramIds = array_values(array_unique(array_merge($selectedProgramIds, $projectProgramIds)));
        }

        $agreement->organizations()->sync($validated['organization_ids'] ?? []);
        $agreement->states()->sync($validated['state_ids'] ?? []);
        $agreement->programs()->sync($selectedProgramIds);
        $agreement->users()->sync($validated['user_ids'] ?? []);
        $agreement->teams()->sync($validated['team_ids'] ?? []);
        
        // Sync logging fields with is_required pivot data
        $loggingFieldIds = $validated['agreement_logging_field_ids'] ?? [];
        $requiredFieldIds = $validated['required_agreement_logging_field_ids'] ?? [];
        $syncData = [];
        foreach ($loggingFieldIds as $fieldId) {
            $syncData[$fieldId] = ['is_required' => in_array($fieldId, $requiredFieldIds)];
        }
        $agreement->agreementLoggingFields()->sync($syncData);
        
        // Handle file uploads
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

        return redirect()
            ->route('agreements.index')
            ->with('success', 'Agreement updated successfully.');
    }

    private function agreementFormData(?Agreement $agreement = null): array
    {
        $states = State::query()->get()->sortBy('name')->values();
        $organizations = Organization::query()->with('states')->get()->sortBy('name')->values();
        $users = User::query()->get()->sortBy('name')->values();
        $teams = Team::query()->where('active', true)->get()->sortBy('name')->values();
        $contactFamilies = ContactFamily::query()->where('active', true)->get()->sortBy(fn ($item) => [$item->sort_order, $item->name])->values();
        $activityTypes = ActivityType::query()->where('active', true)->get()->sortBy(fn ($item) => [$item->sort_order, $item->name])->values();
        $projects = Project::query()->where('active', true)->with('programs')->get()->sortBy('name')->values();
        $programsByProject = Program::query()->where('active', true)->get()->groupBy('project_id');
        $agreementLoggingFields = LoggingField::active()->ordered()->where('available_in_agreements', true)->get();

        if ($agreement) {
            $agreement->load(['users', 'teams', 'deliverables.activityType.contactFamily', 'deliverables.assignedUsers', 'organizations', 'states', 'attachments', 'agreementLoggingFields', 'programs']);
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
            'programsByProject',
            'agreementLoggingFields'
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

    // HTMX endpoint for deliverable management
    public function addDeliverable(Request $request, Agreement $agreement)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can add deliverables.');

        $validated = $request->validate([
            'activity_type_id'    => ['nullable', 'exists:activity_types,id'],
            'contact_family_id'   => ['nullable', 'exists:contact_families,id'],
            'required_hours'      => ['nullable', 'numeric', 'min:0'],
            'required_activities' => ['nullable', 'integer', 'min:0'],
            'notes'               => ['nullable', 'string'],
            'user_ids'            => ['nullable', 'array'],
            'user_ids.*'          => ['exists:users,id'],
        ]);

        $deliverable = $agreement->deliverables()->create(
            collect($validated)->except('user_ids')->toArray()
        );
        $deliverable->assignedUsers()->sync($validated['user_ids'] ?? []);
        $agreement->load('deliverables.activityType.contactFamily.contactFamilyLoggingFields', 'deliverables.assignedUsers');

        return view('agreements.partials.deliverable-list', compact('agreement'));
    }

    public function removeDeliverable(Request $request, Agreement $agreement, AgreementDeliverable $deliverable)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can remove deliverables.');

        // Ensure deliverable belongs to this agreement
        abort_unless($deliverable->agreement_id === $agreement->id, 403, 'Invalid deliverable.');

        AgreementDeliverable::destroy($deliverable->id);
        $agreement->load('deliverables.activityType.contactFamily', 'deliverables.assignedUsers');

        return view('agreements.partials.deliverable-list', compact('agreement'));
    }

    public function editDeliverable(Request $request, Agreement $agreement, AgreementDeliverable $deliverable)
    {
        abort_unless(Auth::user()->isAdmin(), 403);
        abort_unless($deliverable->agreement_id === $agreement->id, 403);

        $contactFamilies = ContactFamily::query()->where('active', true)->get()->sortBy(fn ($item) => [$item->sort_order, $item->name])->values();
        $activityTypes = $deliverable->contact_family_id
            ? ActivityType::query()->where('contact_family_id', $deliverable->contact_family_id)->where('active', true)->get()->sortBy(fn ($item) => [$item->sort_order, $item->name])->values()
            : collect();
        $users = User::query()->get(['id', 'name', 'role'])->sortBy('name')->values();
        $assignedUserIds = $deliverable->assignedUsers()->pluck('users.id')->all();

        return view('agreements.partials.deliverable-row-edit', compact('agreement', 'deliverable', 'contactFamilies', 'activityTypes', 'users', 'assignedUserIds'));
    }

    public function updateDeliverable(Request $request, Agreement $agreement, AgreementDeliverable $deliverable)
    {
        abort_unless(Auth::user()->isAdmin(), 403);
        abort_unless($deliverable->agreement_id === $agreement->id, 403);

        $validated = $request->validate([
            'activity_type_id'    => ['nullable', 'exists:activity_types,id'],
            'contact_family_id'   => ['nullable', 'exists:contact_families,id'],
            'required_hours'      => ['nullable', 'numeric', 'min:0', 'max:99999'],
            'required_activities' => ['nullable', 'integer', 'min:0', 'max:99999'],
            'notes'               => ['nullable', 'string', 'max:500'],
            'user_ids'            => ['nullable', 'array'],
            'user_ids.*'          => ['exists:users,id'],
        ]);

        $deliverable->update(collect($validated)->except('user_ids')->toArray());
        $deliverable->assignedUsers()->sync($validated['user_ids'] ?? []);
        $agreement->load('deliverables.activityType.contactFamily', 'deliverables.assignedUsers');

        return response()
            ->view('agreements.partials.deliverable-list', compact('agreement'))
            ->header('HX-Trigger', 'closeDeliverableModal');
    }

    public function showDeliverableRow(Request $request, Agreement $agreement, AgreementDeliverable $deliverable)
    {
        abort_unless(Auth::user()->isAdmin(), 403);
        abort_unless($deliverable->agreement_id === $agreement->id, 403);

        $agreement->load('deliverables.activityType.contactFamily', 'deliverables.assignedUsers');

        return view('agreements.partials.deliverable-list', compact('agreement'));
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

    /**
     * Delete an agreement attachment.
     */
    public function destroyAttachment(Agreement $agreement, $attachmentId)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can delete attachments.');
        
        $attachment = $agreement->attachments()->findOrFail($attachmentId);
        $attachment->delete(); // Will trigger model event to delete physical file
        
        return redirect()
            ->route('agreements.edit', $agreement)
            ->with('success', 'Attachment deleted successfully.');
    }

}

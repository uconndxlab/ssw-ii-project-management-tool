<?php

namespace App\Http\Controllers;

use App\Http\Requests\AgreementRequest;
use App\Models\Organization;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Project;
use App\Models\Program;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            $this->syncAgreementCertificationCandidates($agreement, $validated['certification_candidates'] ?? []);
            $this->syncAgreementDeliverables($agreement, $validated['deliverables'] ?? []);

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

        $agreement->load(['organizations', 'states', 'users', 'teams.users', 'deliverables.activityType.contactFamily', 'deliverables.assignedUsers', 'attachments', 'certificationCandidates', 'principalInvestigators']);

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
            $this->syncAgreementCertificationCandidates($agreement, $validated['certification_candidates'] ?? []);
            $this->syncAgreementDeliverables($agreement, $validated['deliverables'] ?? []);
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

    private function syncAgreementDeliverables(Agreement $agreement, array $deliverables): void
    {
        $existingDeliverables = $agreement->deliverables()->with('assignedUsers')->get()->keyBy('id');

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
                    $deliverable->assignedUsers()->detach();
                    $deliverable->delete();
                }

                continue;
            }

            if (!$rowHasContent) {
                continue;
            }

            $data = [
                'activity_type_id' => $row['activity_type_id'] ?? null,
                'contact_family_id' => $row['contact_family_id'] ?? null,
                'required_hours' => $row['required_hours'] ?? null,
                'required_activities' => $row['required_activities'] ?? null,
                'notes' => $row['notes'] ?? null,
            ];

            if ($rowId && $existingDeliverables->has($rowId)) {
                $deliverable = $existingDeliverables->get($rowId);
                $deliverable->update($data);
            } else {
                $deliverable = $agreement->deliverables()->create($data);
            }

            $deliverable->assignedUsers()->sync($row['user_ids'] ?? []);
        }
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
            $row['required_hours'] ?? null,
            $row['required_activities'] ?? null,
            $row['notes'] ?? null,
        ];

        foreach ($fields as $value) {
            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return !empty($row['user_ids']);
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
                'deliverables.assignedUsers.programs',
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

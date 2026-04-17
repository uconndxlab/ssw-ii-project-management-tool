<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use App\Models\State;
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
        $organizationsQuery = Organization::query()->orderBy('name');

        if ($request->filled('state_id')) {
            $stateId = $request->integer('state_id');

            $organizationsQuery->whereHas('agreements', function ($q) use ($stateId) {
                $q->where('state_id', $stateId);

                if (!Auth::user()->isAdmin()) {
                    $q->whereHas('users', function ($userQuery) {
                        $userQuery->where('user_id', Auth::id());
                    });
                }
            });
        }

        $organizations = $organizationsQuery->get(['id', 'name']);
        $states = State::orderBy('name')->get(['id', 'name']);

        $query = Agreement::with(['organization', 'state', 'users']);

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
                    ->orWhereHas('organization', function ($orgQuery) use ($search) {
                        $orgQuery->where('name', 'like', "%{$search}%");
                    })
                    ->orWhereHas('state', function ($stateQuery) use ($search) {
                        $stateQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Cascading filters
        if ($request->filled('state_id')) {
            $query->where('state_id', $request->integer('state_id'));
        }

        if ($request->filled('organization_id')) {
            $query->where('organization_id', $request->integer('organization_id'));
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

        $states = State::orderBy('name')->get();
        $organizations = Organization::orderBy('name')->get();
        $users = User::orderBy('name')->get();
        
        return view('agreements.create', compact('states', 'organizations', 'users'));
    }

    public function store(Request $request)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can create agreements.');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'state_id' => ['required', 'exists:states,id'],
            'abstract' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'original_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'extended_end_date' => ['nullable', 'date', 'after_or_equal:original_end_date'],
            'certification_candidates' => ['nullable', 'string'],

            'activity_logging_config' => ['nullable', 'array'],
            'activity_logging_config.event_hours' => ['nullable', 'boolean'],
            'activity_logging_config.prep_hours' => ['nullable', 'boolean'],
            'activity_logging_config.followup_hours' => ['nullable', 'boolean'],
            'activity_logging_config.participant_count' => ['nullable', 'boolean'],
            'activity_logging_config.external_attendees' => ['nullable', 'boolean'],
            'activity_logging_config.summary' => ['nullable', 'boolean'],
            'activity_logging_config.follow_up' => ['nullable', 'boolean'],
            'activity_logging_config.strengths' => ['nullable', 'boolean'],
            'activity_logging_config.recommendations' => ['nullable', 'boolean'],

            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $activityLoggingConfig = $this->normalizeActivityLoggingConfig(
            $request->input('activity_logging_config', [])
        );

        $agreement = Agreement::create([
            'name' => $validated['name'],
            'organization_id' => $validated['organization_id'],
            'state_id' => $validated['state_id'],
            'abstract' => $validated['abstract'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'original_end_date' => $validated['original_end_date'] ?? null,
            'extended_end_date' => $validated['extended_end_date'] ?? null,
            'certification_candidates' => $validated['certification_candidates'] ?? null,
            'activity_logging_config' => $activityLoggingConfig,
        ]);

        if (!empty($validated['user_ids'])) {
            $agreement->users()->sync($validated['user_ids']);
        }

        return redirect()
            ->route('agreements.index')
            ->with('success', 'Agreement created successfully.');
    }

    public function show(Agreement $agreement)
    {
        // Visibility enforcement
        if (!Auth::user()->isAdmin() && !$agreement->users->contains(Auth::id())) {
            abort(403, 'Unauthorized access to this agreement.');
        }

        $agreement->load(['organization', 'state', 'users', 'deliverables.activityType.contactFamily']);
        
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
            'hours' => $activities->sum(fn($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
            'participants' => $activities->sum('participant_count'),
        ];
        
        // YTD totals (current year)
        $ytdActivities = $activities->filter(fn($e) => $e->engagement_date->year === now()->year);
        $ytdTotals = [
            'activities' => $ytdActivities->count(),
            'hours' => $ytdActivities->sum(fn($e) => $e->event_hours + ($e->prep_hours ?? 0) + ($e->followup_hours ?? 0)),
            'participants' => $ytdActivities->sum('participant_count'),
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
                'completed_hours' => $matchingActivities->sum(fn($a) => $a->event_hours + ($a->prep_hours ?? 0) + ($a->followup_hours ?? 0)),
                'completed_activities' => $matchingActivities->count(),
            ];
        });
        
        return view('agreements.show', compact('agreement', 'recentActivities', 'programs', 'lifetimeTotals', 'ytdTotals', 'deliverableProgress'));
    }

    public function edit(Agreement $agreement)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can edit agreements.');

        $states = State::orderBy('name')->get();
        $organizations = Organization::orderBy('name')->get();
        $users = User::orderBy('name')->get();
        $contactFamilies = ContactFamily::where('active', true)->orderBy('sort_order')->orderBy('name')->get();
        $activityTypes = ActivityType::where('active', true)->orderBy('sort_order')->orderBy('name')->get();
        $agreement->load(['users', 'deliverables.activityType.contactFamily']);
        
        return view('agreements.edit', compact('agreement', 'states', 'organizations', 'users', 'contactFamilies', 'activityTypes'));
    }

    public function update(Request $request, Agreement $agreement)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can update agreements.');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'organization_id' => ['required', 'exists:organizations,id'],
            'state_id' => ['required', 'exists:states,id'],
            'abstract' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'original_end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'extended_end_date' => ['nullable', 'date', 'after_or_equal:original_end_date'],
            'certification_candidates' => ['nullable', 'string'],

            'activity_logging_config' => ['nullable', 'array'],
            'activity_logging_config.event_hours' => ['nullable', 'boolean'],
            'activity_logging_config.prep_hours' => ['nullable', 'boolean'],
            'activity_logging_config.followup_hours' => ['nullable', 'boolean'],
            'activity_logging_config.participant_count' => ['nullable', 'boolean'],
            'activity_logging_config.external_attendees' => ['nullable', 'boolean'],
            'activity_logging_config.summary' => ['nullable', 'boolean'],
            'activity_logging_config.follow_up' => ['nullable', 'boolean'],
            'activity_logging_config.strengths' => ['nullable', 'boolean'],
            'activity_logging_config.recommendations' => ['nullable', 'boolean'],

            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['exists:users,id'],
        ]);

        $activityLoggingConfig = $this->normalizeActivityLoggingConfig(
            $request->input('activity_logging_config', [])
        );

        $agreement->update([
            'name' => $validated['name'],
            'organization_id' => $validated['organization_id'],
            'state_id' => $validated['state_id'],
            'abstract' => $validated['abstract'] ?? null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'original_end_date' => $validated['original_end_date'] ?? null,
            'extended_end_date' => $validated['extended_end_date'] ?? null,
            'certification_candidates' => $validated['certification_candidates'] ?? null,
            'activity_logging_config' => $activityLoggingConfig,
        ]);

        $agreement->users()->sync($validated['user_ids'] ?? []);

        return redirect()
            ->route('agreements.index')
            ->with('success', 'Agreement updated successfully.');
    }

    public function destroy(Agreement $agreement)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can delete agreements.');

        $agreement->delete();

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
            'activity_type_id' => ['nullable', 'exists:activity_types,id'],
            'contact_family_id' => ['nullable', 'exists:contact_families,id'],
            'required_hours' => ['nullable', 'numeric', 'min:0'],
            'required_activities' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
        ]);

        $agreement->deliverables()->create($validated);
        $agreement->load('deliverables.activityType.contactFamily');

        return view('agreements.partials.deliverable-list', compact('agreement'));
    }

    public function removeDeliverable(Request $request, Agreement $agreement, AgreementDeliverable $deliverable)
    {
        // Admin-only authorization
        abort_unless(Auth::user()->isAdmin(), 403, 'Only administrators can remove deliverables.');

        // Ensure deliverable belongs to this agreement
        abort_unless($deliverable->agreement_id === $agreement->id, 403, 'Invalid deliverable.');

        $deliverable->delete();
        $agreement->load('deliverables.activityType.contactFamily');

        return view('agreements.partials.deliverable-list', compact('agreement'));
    }

    protected function normalizeActivityLoggingConfig(?array $submittedConfig = []): array
    {
        $defaultActivityLoggingConfig = [
            'event_hours' => false,
            'prep_hours' => false,
            'followup_hours' => false,
            'participant_count' => false,
            'external_attendees' => false,
            'summary' => false,
            'follow_up' => false,
            'strengths' => false,
            'recommendations' => false,
        ];

        return array_merge(
            $defaultActivityLoggingConfig,
            collect($submittedConfig ?? [])
                ->map(fn ($value) => (bool) $value)
                ->toArray()
        );
    }
}

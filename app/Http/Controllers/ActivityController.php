<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\State;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        $contactFamilies = ContactFamily::where('active', true)
            ->with('contactFamilyLoggingFields')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Pre-load organizations, states, and deliverables
        $agreements->load(['organizations', 'states', 'deliverables.activityType']);

        // Get pre-selected agreement if provided
        $preselectedAgreementId = $request->query('agreement_id');
        $duplicateData = session('duplicate_data', []);
        $currentContactFamilyId = old('contact_family_id', $duplicateData['contact_family_id'] ?? null);

        return view('activities.create', compact(
            'agreements',
            'states',
            'organizations',
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
            'internal_only' => ['nullable', 'boolean'],
            'agreement_logging_values' => ['nullable', 'array'],
            'contact_family_logging_values' => ['nullable', 'array'],
        ]);

        $agreements = $this->resolveSelectedAgreements($baseValidated['agreement_ids'] ?? []);
        $contactFamily = ContactFamily::with('contactFamilyLoggingFields')
            ->findOrFail($baseValidated['contact_family_id']);

        $validated = array_merge(
            $baseValidated,
            $request->validate(
                $this->dynamicLoggingFieldValidationRules($agreements, $contactFamily)
            )
        );

        $this->validateAgreementCoverageSelections($validated, $agreements);
        $this->validateAgreementClassificationSelections($validated, $agreements);

        $activity = Activity::create([
            'user_id' => Auth::id(),
            'engagement_date' => $validated['engagement_date'],
            'activity_type_id' => $validated['activity_type_id'],
            'internal_only' => $validated['internal_only'] ?? false,
        ]);

        $this->syncLoggingFieldAnswers($activity, $validated, $agreements, $contactFamily);

        $activity->agreements()->sync($validated['agreement_ids'] ?? []);
        $activity->states()->sync($validated['state_ids'] ?? []);
        $activity->organizations()->sync($validated['organization_ids'] ?? []);

        $saveMode = $request->input('save_mode', 'save');

        if ($saveMode === 'save_new') {
            return redirect()
                ->route('activities.create')
                ->with('success', 'Activity logged. Ready for a new entry.');
        }

        if ($saveMode === 'save_duplicate') {
            $duplicateData = [
                'agreement_ids' => $validated['agreement_ids'] ?? [],
                'state_ids' => $validated['state_ids'] ?? [],
                'organization_ids' => $validated['organization_ids'] ?? [],
                'contact_family_id' => $validated['contact_family_id'] ?? null,
                'activity_type_id' => $validated['activity_type_id'] ?? null,
                'engagement_date' => now()->format('Y-m-d'),
                'internal_only' => $validated['internal_only'] ?? false,
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

        $activity->load(['agreements.organizations', 'agreements.states', 'organizations', 'states', 'user', 'programs', 'participants', 'activityType.contactFamily', 'loggingFieldAnswers']);
        $activity->load(['agreements.agreementLoggingFields', 'activityType.contactFamily.contactFamilyLoggingFields']);

        return view('activities.show', compact('activity'));
    }

    public function edit(Activity $activity)
    {
        $this->verifyActivityEditAccess($activity);

        $agreements = $this->getVisibleAgreements()->load('agreementLoggingFields');
        $states = State::orderBy('name')->get();
        $organizations = Organization::orderBy('name')->get();
        $contactFamilies = ContactFamily::where('active', true)
            ->with('contactFamilyLoggingFields')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $agreements->load(['organizations', 'states', 'deliverables.activityType']);
        $activity->load(['activityType.contactFamily', 'agreements', 'states', 'organizations', 'loggingFieldAnswers']);
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
            'internal_only' => ['nullable', 'boolean'],
            'agreement_logging_values' => ['nullable', 'array'],
            'contact_family_logging_values' => ['nullable', 'array'],
        ]);

        $agreements = $this->resolveSelectedAgreements($baseValidated['agreement_ids'] ?? []);
        $contactFamily = ContactFamily::with('contactFamilyLoggingFields')
            ->findOrFail($baseValidated['contact_family_id']);

        $validated = array_merge(
            $baseValidated,
            $request->validate(
                $this->dynamicLoggingFieldValidationRules($agreements, $contactFamily)
            )
        );

        $this->validateAgreementCoverageSelections($validated, $agreements);
        $this->validateAgreementClassificationSelections($validated, $agreements);

        $activity->update([
            'engagement_date' => $validated['engagement_date'],
            'activity_type_id' => $validated['activity_type_id'],
            'internal_only' => $validated['internal_only'] ?? false,
        ]);

        $this->syncLoggingFieldAnswers($activity, $validated, $agreements, $contactFamily);

        $activity->agreements()->sync($validated['agreement_ids'] ?? []);
        $activity->states()->sync($validated['state_ids'] ?? []);
        $activity->organizations()->sync($validated['organization_ids'] ?? []);
        $saveMode = $request->input('save_mode', 'save');

        if ($saveMode === 'save_new') {
            return redirect()
                ->route('activities.create')
                ->with('success', 'Activity updated. Ready for a new entry.');
        }

        if ($saveMode === 'save_duplicate') {
            $duplicateData = [
                'agreement_ids' => $validated['agreement_ids'] ?? [],
                'state_ids' => $validated['state_ids'] ?? [],
                'organization_ids' => $validated['organization_ids'] ?? [],
                'contact_family_id' => $validated['contact_family_id'] ?? null,
                'activity_type_id' => $validated['activity_type_id'] ?? null,
                'engagement_date' => now()->format('Y-m-d'),
                'internal_only' => $validated['internal_only'] ?? false,
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

    private function validateAgreementCoverageSelections(array $validated, $agreements): void
    {
        if (empty($validated['agreement_ids'])) {
            return;
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

        $selectedOrganizationIds = collect($validated['organization_ids'] ?? [])->map(fn ($id) => (int) $id);
        $selectedStateIds = collect($validated['state_ids'] ?? [])->map(fn ($id) => (int) $id);

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

    private function rulesForField(string $fieldType, bool $required, array $options = []): array
    {
        $prefix = $required ? ['required'] : ['nullable'];

        return match ($fieldType) {
            'number' => array_merge($prefix, ['integer']),
            'decimal' => array_merge($prefix, ['numeric']),
            'checkbox' => array_merge($prefix, ['boolean']),
            'select' => array_merge($prefix, [Rule::in($options)]),
            'document' => array_merge($required ? ['required'] : ['nullable'], ['file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:10240']),
            'textarea', 'text' => array_merge($prefix, ['string', 'max:5000']),
            default => array_merge($prefix, ['string', 'max:5000']),
        };
    }

    private function syncLoggingFieldAnswers(Activity $activity, array $validated, $agreements, ContactFamily $contactFamily): void
    {
        $activity->loadMissing('loggingFieldAnswers');

        $existingAgreementValues = $activity->agreement_logging_values;
        $existingContactFamilyValues = $activity->contact_family_logging_values;
        $payload = [];

        foreach ($agreements as $agreement) {
            foreach ($agreement->agreementLoggingFields as $field) {
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

        foreach ($contactFamily->contactFamilyLoggingFields as $field) {
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
        $contextId = $context === 'agreement'
            ? $agreementId
            : $activity->activityType()->value('contact_family_id');

        $path = $activity->loggingFieldAnswers()
            ->where('logging_field_id', $fieldId)
            ->where('context_type', $context)
            ->where('context_id', $contextId)
            ->value('file_path');

        abort_unless($path && Storage::exists($path), 404);

        return Storage::download($path);
    }
}

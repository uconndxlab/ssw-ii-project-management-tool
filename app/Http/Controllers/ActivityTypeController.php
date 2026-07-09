<?php

namespace App\Http\Controllers;

use App\Models\AgreementDeliverable;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Support\ProjectProgramScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ActivityTypeController extends Controller
{
    public function __construct()
    {
        abort_unless(Auth::user()?->isAdmin(), 403);
    }

    public function index(Request $request)
    {
        $contactFamilies = ContactFamily::query()->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get(['id', 'name']);

        $query = ActivityType::with('contactFamily');

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhereHas('contactFamily', function ($familyQuery) use ($search) {
                        $familyQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filters
        if ($request->filled('contact_family_id')) {
            $query->where('contact_family_id', $request->integer('contact_family_id'));
        }

        if ($request->filled('active')) {
            $query->where('active', $request->input('active') === '1');
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        switch ($sort) {
            case 'contact_family':
                $query->join('contact_families', 'activity_types.contact_family_id', '=', 'contact_families.id')
                    ->select('activity_types.*')
                    ->orderBy('contact_families.sort_order')
                    ->orderBy('contact_families.name', $direction)
                    ->orderBy('activity_types.sort_order')
                    ->orderBy('activity_types.name');
                break;

            case 'duration_days':
                $query->orderBy('duration_days', $direction)
                    ->orderBy('name');
                break;

            case 'duration_hours':
                $query->orderBy('duration_hours', $direction)
                    ->orderBy('name');
                break;

            case 'active':
                $query->orderBy('active', $direction)
                    ->orderBy('name');
                break;

            case 'name':
            default:
                $query->orderBy('name', $direction);
                break;
        }

        $activityTypes = $query->paginate(20)->withQueryString();

        if ($request->header('HX-Request') === 'true' && $request->input('partial') === 'filters') {
            return view('admin.activity-types.partials.filters', compact('contactFamilies', 'sort', 'direction'));
        }

        if ($request->header('HX-Request') === 'true') {
            return view('admin.activity-types.partials.table', compact('activityTypes', 'sort', 'direction'));
        }

        return view('admin.activity-types.index', compact(
            'activityTypes',
            'contactFamilies',
            'sort',
            'direction'
        ));
    }

    public function create()
    {
        $contactFamilies = ContactFamily::query()->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();
        $activityTypeLoggingFields = LoggingField::active()
            ->ordered()
            ->where('available_in_activities', true)
            ->get();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();

        return view('admin.activity-types.create', compact('contactFamilies', 'activityTypeLoggingFields', 'projects'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'contact_family_id' => ['required', 'exists:contact_families,id'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:0'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
            'activity_type_logging_field_ids' => ['nullable', 'array'],
            'activity_type_logging_field_ids.*' => ['exists:logging_fields,id'],
            'required_activity_type_logging_field_ids' => ['nullable', 'array'],
            'required_activity_type_logging_field_ids.*' => ['exists:logging_fields,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            ProjectProgramScope::validateSelection(
                $validator,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
        });

        $validated = $validator->validate();

        $exists = ActivityType::query()->where('contact_family_id', $validated['contact_family_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'An activity type with this name already exists in the selected contact family.'
                ]);
        }

        $validated['active'] = $request->has('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['duration_days'] = $validated['duration_days'] ?? 0;
        $validated['duration_hours'] = $validated['duration_hours'] ?? 0;

        $activityType = ActivityType::create($validated);
        $activityType->projects()->sync(ProjectProgramScope::normalizeIds($validated['project_ids'] ?? []));
        $activityType->programs()->sync(ProjectProgramScope::normalizeIds($validated['program_ids'] ?? []));

        $syncData = [];
        foreach (($validated['activity_type_logging_field_ids'] ?? []) as $fieldId) {
            $syncData[$fieldId] = [
                'is_required' => in_array($fieldId, $validated['required_activity_type_logging_field_ids'] ?? []),
            ];
        }
        $activityType->activityTypeLoggingFields()->sync($syncData);

        return redirect()
            ->route('activity-types.index')
            ->with('success', 'Activity type created successfully.');
    }

    public function edit(ActivityType $activityType)
    {
        $contactFamilies = ContactFamily::query()->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();
        $activityTypeLoggingFields = LoggingField::active()
            ->ordered()
            ->where('available_in_activities', true)
            ->get();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();
        $activityType->load(['activityTypeLoggingFields', 'projects', 'programs']);

        return view('admin.activity-types.edit', compact('activityType', 'contactFamilies', 'activityTypeLoggingFields', 'projects'));
    }

    public function update(Request $request, ActivityType $activityType)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'contact_family_id' => ['required', 'exists:contact_families,id'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'duration_days' => ['nullable', 'integer', 'min:0'],
            'duration_hours' => ['nullable', 'integer', 'min:0'],
            'activity_type_logging_field_ids' => ['nullable', 'array'],
            'activity_type_logging_field_ids.*' => ['exists:logging_fields,id'],
            'required_activity_type_logging_field_ids' => ['nullable', 'array'],
            'required_activity_type_logging_field_ids.*' => ['exists:logging_fields,id'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ]);

        $validator->after(function ($validator) use ($request) {
            ProjectProgramScope::validateSelection(
                $validator,
                ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
                ProjectProgramScope::normalizeIds($request->input('program_ids', []))
            );
        });

        $validated = $validator->validate();

        $exists = ActivityType::query()->where('contact_family_id', $validated['contact_family_id'])
            ->where('name', $validated['name'])
            ->where('id', '!=', $activityType->id)
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'An activity type with this name already exists in the selected contact family.'
                ]);
        }

        $validated['active'] = $request->has('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['duration_days'] = $validated['duration_days'] ?? 0;
        $validated['duration_hours'] = $validated['duration_hours'] ?? 0;

        $activityType->update($validated);
        $activityType->projects()->sync(ProjectProgramScope::normalizeIds($validated['project_ids'] ?? []));
        $activityType->programs()->sync(ProjectProgramScope::normalizeIds($validated['program_ids'] ?? []));

        $syncData = [];
        foreach (($validated['activity_type_logging_field_ids'] ?? []) as $fieldId) {
            $syncData[$fieldId] = [
                'is_required' => in_array($fieldId, $validated['required_activity_type_logging_field_ids'] ?? []),
            ];
        }
        $activityType->activityTypeLoggingFields()->sync($syncData);

        return redirect()
            ->route('activity-types.index')
            ->with('success', 'Activity type updated successfully.');
    }

    public function destroy(ActivityType $activityType)
    {
        if ($activityType->activities()->count() > 0) {
            return redirect()
                ->route('activity-types.index')
                ->with('error', 'Cannot delete activity type that is used in activities.');
        }

        ActivityType::destroy($activityType->id);

        return redirect()
            ->route('activity-types.index')
            ->with('success', 'Activity type deleted successfully.');
    }

    public function getByFamily(Request $request)
    {
        $contactFamilyId = $request->input('contact_family_id');
        $selectedActivityTypeId = (int) $request->input('activity_type_id');
        $agreementIds = collect($request->input('agreement_ids', []))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if (!$contactFamilyId) {
            return response('<option value="">Select activity type...</option>');
        }

        $query = ActivityType::query()->where('contact_family_id', $contactFamilyId)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($agreementIds->isNotEmpty()) {
            $deliverables = AgreementDeliverable::query()
                ->with('activityType:id,contact_family_id')
                ->whereIn('agreement_id', $agreementIds)
                ->where(function ($query) use ($contactFamilyId) {
                    $query
                        ->where('contact_family_id', $contactFamilyId)
                        ->orWhereHas('activityType', function ($activityTypeQuery) use ($contactFamilyId) {
                            $activityTypeQuery->where('contact_family_id', $contactFamilyId);
                        });
                })
                ->get();

            $hasFamilyLevelDeliverable = $deliverables->contains(function ($deliverable) use ($contactFamilyId) {
                return (int) $deliverable->contact_family_id === (int) $contactFamilyId
                    && !$deliverable->activity_type_id;
            });

            if (!$hasFamilyLevelDeliverable) {
                $allowedActivityTypeIds = $deliverables
                    ->pluck('activity_type_id')
                    ->filter()
                    ->unique()
                    ->values();

                $query->whereIn('id', $allowedActivityTypeIds->all());
            }
        }

        $activityTypes = $query->get();

        $html = '<option value="">Select activity type...</option>';
        foreach ($activityTypes as $type) {
            $selected = $selectedActivityTypeId === (int) $type->id ? ' selected' : '';
            $html .= '<option value="' . $type->id . '"' . $selected . '>' . e($type->name) . '</option>';
        }

        return response($html);
    }
}

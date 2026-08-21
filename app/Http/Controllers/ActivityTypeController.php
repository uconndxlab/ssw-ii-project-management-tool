<?php

namespace App\Http\Controllers;

use App\Enums\ProgramScopeMode;
use App\Models\AgreementDeliverable;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Program;
use App\Models\Project;
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

        $query = ActivityType::query()->with(['contactFamily', 'programs.projects']);

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereIlike('activity_types.name', "%{$search}%")
                    ->orWhereHas('contactFamily', function ($familyQuery) use ($search) {
                        $familyQuery->whereIlike('name', "%{$search}%");
                    });
            });
        }

        // Filters
        if ($request->filled('contact_family_id')) {
            $query->where('activity_types.contact_family_id', $request->integer('contact_family_id'));
        }

        if ($request->filled('active')) {
            $query->where('activity_types.active', $request->input('active') === '1');
        }

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('programs.projects', fn ($relation) => $relation->where('projects.id', $projectId))
                    ->orWhere('activity_types.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhere('activity_types.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $this->applyActivityTypeIndexSort($query, $sort, $direction);

        $activityTypes = $query->paginate(20)->withQueryString();

        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);

        if ($request->header('HX-Request') === 'true' && $request->input('partial') === 'filters') {
            return view('admin.activity-types.partials.filters', compact(
                'contactFamilies',
                'sort',
                'direction',
                'filterProjects',
                'filterPrograms',
            ));
        }

        if ($request->header('HX-Request') === 'true') {
            return view('admin.activity-types.partials.table', compact('activityTypes', 'sort', 'direction'));
        }

        return view('admin.activity-types.index', compact(
            'activityTypes',
            'contactFamilies',
            'sort',
            'direction',
            'filterProjects',
            'filterPrograms',
        ));
    }

    private function applyActivityTypeIndexSort($query, string $sort, string $direction): void
    {
        $dir = $direction === 'desc' ? 'DESC' : 'ASC';

        switch ($sort) {
            case 'contact_family':
                $query->join('contact_families', 'activity_types.contact_family_id', '=', 'contact_families.id')
                    ->select('activity_types.*')
                    ->orderBy('contact_families.name', $direction)
                    ->orderBy('contact_families.sort_order', $direction)
                    ->orderBy('activity_types.name', $direction);
                break;

            case 'duration_days':
                $query->orderBy('activity_types.duration_days', $direction)
                    ->orderBy('activity_types.name');
                break;

            case 'duration_hours':
                $query->orderBy('activity_types.duration_hours', $direction)
                    ->orderBy('activity_types.name');
                break;

            case 'active':
                $query->orderBy('activity_types.active', $direction)
                    ->orderBy('activity_types.name');
                break;

            case 'projects':
                $query->orderByRaw($this->minActivityTypeProjectNameSql()." {$dir}")
                    ->orderBy('activity_types.name');
                break;

            case 'programs':
                $query->orderByRaw($this->minActivityTypeProgramNameSql()." {$dir}")
                    ->orderBy('activity_types.name');
                break;

            case 'name':
            default:
                $query->orderBy('activity_types.name', $direction);
                break;
        }
    }

    private function minActivityTypeProjectNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM projects p
            INNER JOIN program_project pp ON pp.project_id = p.id
            INNER JOIN activity_type_program atp ON atp.program_id = pp.program_id AND atp.activity_type_id = activity_types.id
        ), '')";
    }

    private function minActivityTypeProgramNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM programs p
            INNER JOIN activity_type_program atp ON atp.program_id = p.id AND atp.activity_type_id = activity_types.id
        ), '')";
    }

    public function create()
    {
        $contactFamilies = ContactFamily::query()->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();
        $activityTypeLoggingFields = LoggingField::active()
            ->ordered()
            ->where('available_in_activities', true)
            ->with('programs')
            ->get();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();

        return view('admin.activity-types.create', compact('contactFamilies', 'activityTypeLoggingFields', 'projects'));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), $this->activityTypeValidationRules());

        $this->addActivityTypeValidatorAfter($validator, $request);

        $validated = $validator->validate();

        $exists = ActivityType::query()->where('contact_family_id', $validated['contact_family_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'An activity type with this name already exists in the selected activity family.'
                ]);
        }

        $validated['active'] = $request->boolean('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated = $this->normalizeValidatedDuration($validated);
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, ActivityType::class)->value;

        $activityType = ActivityType::create($validated);
        $activityType->programs()->sync(ProjectProgramScope::modeAwareProgramIds(
            $validated['program_scope_mode'],
            ActivityType::class,
            $validated['project_ids'] ?? [],
            $validated['program_ids'] ?? []
        ));

        $syncData = [];
        foreach (array_values(array_unique($validated['activity_type_logging_field_ids'] ?? [])) as $index => $fieldId) {
            $syncData[$fieldId] = [
                'is_required' => in_array($fieldId, $validated['required_activity_type_logging_field_ids'] ?? []),
                'sort_order' => $index + 1,
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
            ->with('programs')
            ->get();
        $projects = ProjectProgramScope::activeProjectsWithPrograms();
        $activityType->load(['activityTypeLoggingFields', 'programs.projects']);

        return view('admin.activity-types.edit', compact('activityType', 'contactFamilies', 'activityTypeLoggingFields', 'projects'));
    }

    public function update(Request $request, ActivityType $activityType)
    {
        $validator = Validator::make($request->all(), $this->activityTypeValidationRules());

        $this->addActivityTypeValidatorAfter($validator, $request);

        $validated = $validator->validate();

        $exists = ActivityType::query()->where('contact_family_id', $validated['contact_family_id'])
            ->where('name', $validated['name'])
            ->where('id', '!=', $activityType->id)
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'An activity type with this name already exists in the selected activity family.'
                ]);
        }

        $validated['active'] = $request->boolean('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated = $this->normalizeValidatedDuration($validated);
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, ActivityType::class)->value;

        $activityType->update($validated);
        $activityType->programs()->sync(ProjectProgramScope::modeAwareProgramIds(
            $validated['program_scope_mode'],
            ActivityType::class,
            $validated['project_ids'] ?? [],
            $validated['program_ids'] ?? []
        ));

        $syncData = [];
        foreach (array_values(array_unique($validated['activity_type_logging_field_ids'] ?? [])) as $index => $fieldId) {
            $syncData[$fieldId] = [
                'is_required' => in_array($fieldId, $validated['required_activity_type_logging_field_ids'] ?? []),
                'sort_order' => $index + 1,
            ];
        }
        $activityType->activityTypeLoggingFields()->sync($syncData);

        return redirect()
            ->route('activity-types.index')
            ->with('success', 'Activity type updated successfully.');
    }

    public function destroy(ActivityType $activityType)
    {
        $isUsedInActivities = Activity::query()
            ->where('activity_type_id', $activityType->getKey())
            ->exists();

        if ($isUsedInActivities) {
            return redirect()
                ->route('activity-types.index')
                ->with('error', 'Cannot delete activity type that is used in activities.');
        }

        ActivityType::query()->whereKey($activityType->getKey())->delete();

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
            $durationHours = (float) $type->duration_hours > 0 ? $type->duration_hours : '';
            $durationDays = (float) $type->duration_days > 0 ? $type->duration_days : '';
            $html .= '<option value="' . $type->id . '"'
                . ' data-duration-hours="' . e((string) $durationHours) . '"'
                . ' data-duration-days="' . e((string) $durationDays) . '"'
                . ' data-helper-text="' . e((string) ($type->helper_text ?? '')) . '"'
                . $selected . '>' . e($type->name) . '</option>';
        }

        return response($html);
    }

    private function activityTypeValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'helper_text' => ['nullable', 'string', 'max:1000'],
            'contact_family_id' => ['required', 'exists:contact_families,id'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'duration_unit' => ['required', 'in:none,days,hours'],
            'duration_value' => ['nullable', 'numeric', 'min:0', 'multiple_of:0.5', 'required_if:duration_unit,days,hours'],
            'activity_type_logging_field_ids' => ['nullable', 'array'],
            'activity_type_logging_field_ids.*' => ['exists:logging_fields,id'],
            'required_activity_type_logging_field_ids' => ['nullable', 'array'],
            'required_activity_type_logging_field_ids.*' => ['exists:logging_fields,id'],
            'program_scope_mode' => ['required', 'in:all,specific,none'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ];
    }

    private function addActivityTypeValidatorAfter($validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            $mode = $request->input('program_scope_mode', ProgramScopeMode::All->value);
            $projectIds = ProjectProgramScope::normalizeIds($request->input('project_ids', []));
            $programIds = ProjectProgramScope::normalizeIds($request->input('program_ids', []));

            ProjectProgramScope::validateModeSelection($validator, $mode, ActivityType::class, $projectIds, $programIds);

            if (ProjectProgramScope::normalizeMode($mode, ActivityType::class) !== ProgramScopeMode::Specific) {
                return;
            }

            ProjectProgramScope::validateScopedAssignments(
                $validator,
                $programIds,
                ProjectProgramScope::normalizeIds($request->input('activity_type_logging_field_ids', [])),
                LoggingField::class,
                'activity_type_logging_field_ids',
                'Selected logging fields must be global or match one of the selected programs.'
            );

            if ($request->input('duration_unit') === 'none' && (float) $request->input('duration_value', 0) > 0) {
                $validator->errors()->add('duration_value', 'Clear the duration value when None is selected.');
            }
        });
    }

    private function normalizeValidatedDuration(array $validated): array
    {
        $unit = $validated['duration_unit'] ?? 'none';
        $value = round((float) ($validated['duration_value'] ?? 0), 1);

        $validated['duration_days'] = $unit === 'days' ? $value : 0;
        $validated['duration_hours'] = $unit === 'hours' ? $value : 0;

        unset($validated['duration_unit'], $validated['duration_value']);

        return $validated;
    }
}

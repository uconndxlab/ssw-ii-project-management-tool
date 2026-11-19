<?php

namespace App\Http\Controllers;

use App\Enums\ProgramScopeMode;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\AgreementDeliverable;
use App\Models\CertificateRequirement;
use App\Models\LoggingField;
use App\Models\Program;
use App\Models\Project;
use App\Models\SubActivity;
use App\Support\ActivityTypeDuration;
use App\Support\Authorization\ScopeSync;
use App\Support\ProjectProgramScope;
use App\Support\SubActivityOptions;
use App\Support\TablePagination;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SubActivityController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', SubActivity::class);
        $query = SubActivity::query()
            ->visibleTo($this->actor())
            ->with(['activityType.contactFamily', 'programs.projects']);

        // Search
        $search = trim((string) $request->input('search', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereLike('sub_activities.name', "%{$search}%")
                    ->orWhereHas('activityType', function ($familyQuery) use ($search) {
                        $familyQuery->whereLike('name', "%{$search}%");
                    });
            });
        }

        // Filters
        if ($request->filled('activity_type_id')) {
            $query->where('sub_activities.activity_type_id', $request->integer('activity_type_id'));
        }

        if ($request->filled('active')) {
            $query->where('sub_activities.active', $request->input('active') === '1');
        }

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('programs.projects', fn ($relation) => $relation->where('projects.id', $projectId))
                    ->orWhere('sub_activities.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhere('sub_activities.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        $this->applySubActivityIndexSort($query, $sort, $direction);

        $subActivities = $query->paginate(TablePagination::perPage($request))->withQueryString();

        if ($request->header('HX-Request') === 'true' && $request->input('partial') !== 'filters') {
            return view('admin.sub-activities.partials.table', compact('subActivities', 'sort', 'direction'));
        }

        $activityTypes = ActivityType::query()->with('contactFamily')->orderBy('sort_order', 'asc')
            ->orderBy('name', 'asc')
            ->get();
        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);

        if ($request->header('HX-Request') === 'true' && $request->input('partial') === 'filters') {
            return view('admin.sub-activities.partials.filters', compact(
                'activityTypes',
                'sort',
                'direction',
                'filterProjects',
                'filterPrograms',
            ));
        }

        return view('admin.sub-activities.index', compact(
            'subActivities',
            'activityTypes',
            'sort',
            'direction',
            'filterProjects',
            'filterPrograms',
        ));
    }

    /**
     * @param  Builder<SubActivity>  $query
     * @param  'asc'|'desc'  $direction
     */
    private function applySubActivityIndexSort(Builder $query, string $sort, string $direction): void
    {
        switch ($sort) {
            case 'activity_type':
                $query->join('activity_types', 'sub_activities.activity_type_id', '=', 'activity_types.id')
                    ->select('sub_activities.*')
                    ->orderBy('activity_types.name', $direction)
                    ->orderBy('activity_types.sort_order', $direction)
                    ->orderBy('sub_activities.name', $direction);
                break;

            case 'duration_days':
                $query->orderByRaw('CASE WHEN sub_activities.duration_days > 0 OR sub_activities.duration_hours > 0 THEN sub_activities.duration_days ELSE (SELECT duration_days FROM activity_types WHERE activity_types.id = sub_activities.activity_type_id) END'.($direction === 'desc' ? ' DESC' : ' ASC'))
                    ->orderBy('sub_activities.name');
                break;

            case 'duration_hours':
                $query->orderByRaw('CASE WHEN sub_activities.duration_days > 0 OR sub_activities.duration_hours > 0 THEN sub_activities.duration_hours ELSE (SELECT duration_hours FROM activity_types WHERE activity_types.id = sub_activities.activity_type_id) END'.($direction === 'desc' ? ' DESC' : ' ASC'))
                    ->orderBy('sub_activities.name');
                break;

            case 'active':
                $query->orderBy('sub_activities.active', $direction)
                    ->orderBy('sub_activities.name');
                break;

            case 'projects':
                $query->orderByRaw($this->minSubActivityProjectNameSql().($direction === 'desc' ? ' DESC' : ' ASC'))
                    ->orderBy('sub_activities.name');
                break;

            case 'programs':
                $query->orderByRaw($this->minSubActivityProgramNameSql().($direction === 'desc' ? ' DESC' : ' ASC'))
                    ->orderBy('sub_activities.name');
                break;

            case 'name':
            default:
                $query->orderBy('sub_activities.name', $direction);
                break;
        }
    }

    /**
     * @return literal-string
     */
    private function minSubActivityProjectNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM projects p
            INNER JOIN program_project pp ON pp.project_id = p.id
            INNER JOIN sub_activity_program atp ON atp.program_id = pp.program_id AND atp.sub_activity_id = sub_activities.id
        ), '')";
    }

    /**
     * @return literal-string
     */
    private function minSubActivityProgramNameSql(): string
    {
        return "COALESCE((
            SELECT MIN(p.name)
            FROM programs p
            INNER JOIN sub_activity_program atp ON atp.program_id = p.id AND atp.sub_activity_id = sub_activities.id
        ), '')";
    }

    public function create(): View
    {
        $this->authorize('create', SubActivity::class);
        $activityTypes = ActivityType::query()->with('contactFamily')->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();
        $subActivityLoggingFields = LoggingField::active()
            ->ordered()
            ->where('available_in_sub_activities', true)
            ->with('programs')
            ->get();
        $projects = ProjectProgramScope::assignableProjectsWithProgramsFor($this->actor());

        return view('admin.sub-activities.create', compact('activityTypes', 'subActivityLoggingFields', 'projects'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', SubActivity::class);
        $validator = Validator::make($request->all(), $this->subActivityValidationRules());

        $this->addSubActivityValidatorAfter($validator, $request);

        $validated = $validator->validate();

        $exists = SubActivity::query()->where('activity_type_id', $validated['activity_type_id'])
            ->where('name', $validated['name'])
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'A sub-activity with this name already exists in the selected activity type.',
                ]);
        }

        $validated['active'] = $request->boolean('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated = $this->normalizeValidatedDuration($validated);
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, SubActivity::class)->value;

        $subActivity = SubActivity::create($validated);
        ScopeSync::applyTo(
            $this->actor(),
            $subActivity,
            ProgramScopeMode::from($validated['program_scope_mode']),
            $validated['program_ids'] ?? [],
        );

        $syncData = [];
        foreach (array_values(array_unique($validated['sub_activity_logging_field_ids'] ?? [])) as $index => $fieldId) {
            $syncData[$fieldId] = [
                'is_required' => in_array($fieldId, $validated['required_sub_activity_logging_field_ids'] ?? []),
                'sort_order' => $index + 1,
            ];
        }
        $subActivity->subActivityLoggingFields()->sync($syncData);

        return redirect()
            ->route('sub-activities.index')
            ->with('success', 'Sub-activity created successfully.');
    }

    public function edit(SubActivity $subActivity): View
    {
        $this->authorize('update', $subActivity);
        $activityTypes = ActivityType::query()->with('contactFamily')->orderBy('sort_order', 'asc')->orderBy('name', 'asc')->get();
        $subActivityLoggingFields = LoggingField::active()
            ->ordered()
            ->where('available_in_sub_activities', true)
            ->with('programs')
            ->get();
        $projects = ProjectProgramScope::assignableProjectsWithProgramsFor($this->actor(), $subActivity);
        $subActivity->load(['subActivityLoggingFields', 'programs.projects']);

        return view('admin.sub-activities.edit', compact('subActivity', 'activityTypes', 'subActivityLoggingFields', 'projects'));
    }

    public function update(Request $request, SubActivity $subActivity): RedirectResponse
    {
        $this->authorize('update', $subActivity);
        $validator = Validator::make($request->all(), $this->subActivityValidationRules());

        $this->addSubActivityValidatorAfter($validator, $request);

        $validated = $validator->validate();

        $exists = SubActivity::query()->where('activity_type_id', $validated['activity_type_id'])
            ->where('name', $validated['name'])
            ->where('id', '!=', $subActivity->id)
            ->exists();

        if ($exists) {
            return back()
                ->withInput()
                ->withErrors([
                    'name' => 'A sub-activity with this name already exists in the selected activity type.',
                ]);
        }

        // Records store the type and the sub-activity separately, so moving a used one would leave them mismatched.
        if ((int) $validated['activity_type_id'] !== (int) $subActivity->activity_type_id && $this->isUsed($subActivity)) {
            return back()
                ->withInput()
                ->withErrors([
                    'activity_type_id' => 'Cannot move a sub-activity that is used in activities, agreement deliverables or certificate requirements to another activity type.',
                ]);
        }

        $validated['active'] = $request->boolean('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated = $this->normalizeValidatedDuration($validated);
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, SubActivity::class)->value;

        unset($validated['program_scope_mode'], $validated['project_ids'], $validated['program_ids']);
        $subActivity->update($validated);
        ScopeSync::applyTo(
            $this->actor(),
            $subActivity,
            ProgramScopeMode::from(ProjectProgramScope::normalizeMode($request->input('program_scope_mode'), SubActivity::class)->value),
            $request->input('program_ids', []),
        );

        $syncData = [];
        foreach (array_values(array_unique($validated['sub_activity_logging_field_ids'] ?? [])) as $index => $fieldId) {
            $syncData[$fieldId] = [
                'is_required' => in_array($fieldId, $validated['required_sub_activity_logging_field_ids'] ?? []),
                'sort_order' => $index + 1,
            ];
        }
        $subActivity->subActivityLoggingFields()->sync($syncData);

        return $this->redirectAfterSave($subActivity, 'Sub-activity updated successfully.');
    }

    public function destroy(SubActivity $subActivity): RedirectResponse
    {
        $this->authorize('delete', $subActivity);

        if ($this->isUsed($subActivity)) {
            return redirect()
                ->route('sub-activities.index')
                ->with('error', 'Cannot delete sub-activity that is used in activities, agreement deliverables or certificate requirements.');
        }

        SubActivity::query()->whereKey($subActivity->getKey())->delete();

        return redirect()
            ->route('sub-activities.index')
            ->with('success', 'Sub-activity deleted successfully.');
    }

    private function isUsed(SubActivity $subActivity): bool
    {
        return Activity::query()->where('sub_activity_id', $subActivity->id)->exists()
            || AgreementDeliverable::query()->where('sub_activity_id', $subActivity->id)->exists()
            || CertificateRequirement::query()->where('sub_activity_id', $subActivity->id)->exists();
    }

    public function getByType(Request $request): Response
    {
        $this->authorize('create', Activity::class);
        $validated = $request->validate([
            'activity_type_id' => ['nullable', 'integer'],
            'sub_activity_id' => ['nullable', 'integer'],
            'agreement_ids' => ['nullable', 'array'],
            'agreement_ids.*' => ['integer'],
        ]);
        $selectedId = (int) ($validated['sub_activity_id'] ?? 0);
        $options = SubActivityOptions::availableFor((int) ($validated['activity_type_id'] ?? 0), array_map('intval', $validated['agreement_ids'] ?? []), $selectedId ?: null);
        $html = '<option value="">Select sub-activity...</option>';
        foreach ($options as $subActivity) {
            $duration = ActivityTypeDuration::effective($subActivity->activityType, $subActivity);
            $html .= '<option value="'.$subActivity->id.'"'
                .' data-duration-hours="'.e((string) ($duration->unit() === 'hours' ? $duration->value() : '')).'"'
                .' data-duration-days="'.e((string) ($duration->unit() === 'days' ? $duration->value() : '')).'"'
                .' data-duration-source="'.e(ActivityTypeDuration::effectiveSource($subActivity->activityType, $subActivity) ?? '').'"'
                .' data-helper-text="'.e((string) $subActivity->helper_text).'"'
                .($selectedId === $subActivity->id ? ' selected' : '').'>'.e($subActivity->name).'</option>';
        }

        return response($html);
    }

    /**
     * @return array<string, mixed>
     */
    private function subActivityValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'helper_text' => ['nullable', 'string', 'max:1000'],
            'activity_type_id' => ['required', 'exists:activity_types,id'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'duration_unit' => ['required', 'in:none,days,hours'],
            'duration_value' => ['nullable', 'numeric', 'min:0', 'multiple_of:0.5', 'required_if:duration_unit,days,hours'],
            'sub_activity_logging_field_ids' => ['nullable', 'array'],
            'sub_activity_logging_field_ids.*' => [Rule::exists('logging_fields', 'id')->where('available_in_sub_activities', true)->where('is_active', true)],
            'required_sub_activity_logging_field_ids' => ['nullable', 'array'],
            'required_sub_activity_logging_field_ids.*' => [Rule::exists('logging_fields', 'id')->where('available_in_sub_activities', true)->where('is_active', true)],
            'program_scope_mode' => ['required', 'in:all,specific,none'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
        ];
    }

    private function addSubActivityValidatorAfter(\Illuminate\Validation\Validator $validator, Request $request): void
    {
        $validator->after(function ($validator) use ($request) {
            $mode = $request->input('program_scope_mode', ProgramScopeMode::All->value);
            $projectIds = ProjectProgramScope::normalizeIds($request->input('project_ids', []));
            $programIds = ProjectProgramScope::normalizeIds($request->input('program_ids', []));

            ProjectProgramScope::validateModeSelection($validator, $mode, SubActivity::class, $projectIds, $programIds);
            $submittedMode = ProgramScopeMode::tryFrom((string) $mode) ?? ProgramScopeMode::Specific;
            $existing = $request->route('sub_activity');
            ScopeSync::validateSubmittedMode(
                $validator,
                $this->actor(),
                $existing instanceof SubActivity ? $existing->program_scope_mode : ProgramScopeMode::None,
                $submittedMode,
            );
            ScopeSync::validateSubmittedProgramsAreInAdminScope(
                $validator,
                $this->actor(),
                $programIds,
                $existing instanceof SubActivity ? $existing->programs()->pluck('programs.id')->all() : [],
            );

            ProjectProgramScope::validateLoggingFieldsFitScope(
                $validator,
                $this->actor(),
                $existing instanceof SubActivity ? $existing : new SubActivity,
                $mode,
                $programIds,
                ProjectProgramScope::normalizeIds($request->input('sub_activity_logging_field_ids', [])),
                'sub_activity_logging_field_ids',
            );

            if ($request->input('duration_unit') === 'none' && (float) $request->input('duration_value', 0) > 0) {
                $validator->errors()->add('duration_value', 'Clear the duration value when None is selected.');
            }
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
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

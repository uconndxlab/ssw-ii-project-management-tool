<?php

namespace App\Http\Controllers;

use App\Enums\CertificateDimensionRuleMode;
use App\Enums\CertificateGroupSatisfyMode;
use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Enums\ProgramScopeMode;
use App\Models\ActivityType;
use App\Models\Certificate;
use App\Models\CertificateRequirement;
use App\Models\CertificateRequirementDimensionRule;
use App\Models\CertificateRequirementGroup;
use App\Models\CertificationRole;
use App\Models\CertificationTool;
use App\Models\CertificationToolDimension;
use App\Models\ContactFamily;
use App\Models\Program;
use App\Models\Project;
use App\Support\Authorization\ScopeSync;
use App\Support\ProjectProgramScope;
use App\Support\TablePagination;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;

class CertificateController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', Certificate::class);

        $query = Certificate::query()
            ->visibleTo($this->actor())
            ->withCount('requirements')
            ->with(['programs.projects']);

        if ($request->filled('search')) {
            $query->whereIlike('name', '%'.$request->input('search').'%');
        }

        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('programs.projects', fn ($relation) => $relation->where('projects.id', $projectId))
                    ->orWhere('certificates.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhere('certificates.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        match ($sort) {
            'active' => $query->orderBy('certificates.active', $direction)->orderBy('certificates.name'),
            default => $query->orderBy('certificates.sort_order')->orderBy('certificates.name', $direction),
        };

        $certificates = $query->paginate(TablePagination::perPage($request))->withQueryString();

        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);

        if ($request->header('HX-Request')) {
            return view('admin.certificates.partials.table', compact('certificates', 'sort', 'direction'));
        }

        return view('admin.certificates.index', compact(
            'certificates',
            'sort',
            'direction',
            'filterProjects',
            'filterPrograms',
        ));
    }

    public function create(): View|RedirectResponse
    {
        $this->authorize('create', Certificate::class);

        $projects = ProjectProgramScope::assignableProjectsWithProgramsFor($this->actor());

        return view('admin.certificates.create', array_merge(
            compact('projects'),
            $this->sharedFormData(),
        ));
    }

    public function store(Request $request): View|RedirectResponse
    {
        $this->authorize('create', Certificate::class);

        $validated = $this->validateCertificate($request);

        $certificate = DB::transaction(function () use ($validated) {
            $certificate = Certificate::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'active' => $validated['active'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'program_scope_mode' => ProgramScopeMode::Specific,
                'validity_months' => $validated['validity_months'] ?? null,
                'default_window_months' => $validated['default_window_months'] ?? null,
                'prerequisite_mode' => $validated['prerequisite_mode'],
                'renewal_matches_initial' => $validated['renewal_matches_initial'] ?? false,
            ]);

            ScopeSync::applyTo(
                $this->actor(),
                $certificate,
                ProgramScopeMode::from($validated['program_scope_mode']),
                $validated['program_ids'] ?? [],
            );

            $certificate->prerequisites()->sync($validated['prerequisite_certificate_ids'] ?? []);

            $roleIndexToId = $this->syncRoles($certificate, $validated['roles'] ?? []);
            [$groupRows, $requirementRows] = $this->requirementPayloadForSync($validated);
            $groupIndexToId = $this->syncRequirementGroups($certificate, $groupRows);
            $this->syncRequirements($certificate, $requirementRows, $groupIndexToId, $roleIndexToId);

            return $certificate;
        });

        return redirect()
            ->route('certificates.edit', $certificate)
            ->with('success', 'Certificate created successfully.');
    }

    public function edit(Certificate $certificate): View|RedirectResponse
    {
        $this->authorize('update', $certificate);

        $projects = ProjectProgramScope::assignableProjectsWithProgramsFor($this->actor(), $certificate);
        $certificate->load([
            'programs.projects',
            'prerequisites',
            'roles',
            'requirementGroups',
            'requirements.dimensionRules',
        ]);

        return view('admin.certificates.edit', array_merge(
            compact('certificate', 'projects'),
            $this->sharedFormData($certificate),
        ));
    }

    public function update(Request $request, Certificate $certificate): View|RedirectResponse
    {
        $this->authorize('update', $certificate);

        $validated = $this->validateCertificate($request, $certificate);

        DB::transaction(function () use ($validated, $certificate) {
            $certificate->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'active' => $validated['active'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'validity_months' => $validated['validity_months'] ?? null,
                'default_window_months' => $validated['default_window_months'] ?? null,
                'prerequisite_mode' => $validated['prerequisite_mode'],
                'renewal_matches_initial' => $validated['renewal_matches_initial'] ?? false,
            ]);

            ScopeSync::applyTo(
                $this->actor(),
                $certificate,
                ProgramScopeMode::from($validated['program_scope_mode']),
                $validated['program_ids'] ?? [],
            );

            $certificate->prerequisites()->sync($validated['prerequisite_certificate_ids'] ?? []);

            $roleIndexToId = $this->syncRoles($certificate, $validated['roles'] ?? []);
            [$groupRows, $requirementRows] = $this->requirementPayloadForSync($validated);
            $groupIndexToId = $this->syncRequirementGroups($certificate, $groupRows);
            $this->syncRequirements($certificate, $requirementRows, $groupIndexToId, $roleIndexToId);
        });

        return $this->redirectAfterSave($certificate, 'Certificate updated successfully.');
    }

    public function destroy(Certificate $certificate): View|RedirectResponse
    {
        $this->authorize('delete', $certificate);

        if ($certificate->requirements()->exists() || $certificate->requirementGroups()->exists() || $certificate->enrollments()->exists()) {
            $certificate->update(['retired_at' => now(), 'active' => false]);

            return redirect()
                ->route('certificates.index')
                ->with('success', 'Certificate has requirements or assigned candidates, so it was retired instead of deleted.');
        }

        $certificate->delete();

        return redirect()
            ->route('certificates.index')
            ->with('success', 'Certificate deleted successfully.');
    }

    /**
     * Data shared by create/edit views: pickers, tool catalog for dimension rule authoring.
     *
     * @return array<string, mixed>
     */
    private function sharedFormData(?Certificate $certificate = null): array
    {
        $contactFamilies = ContactFamily::orderBy('sort_order')->orderBy('name')->get();
        $activityTypes = ActivityType::orderBy('sort_order')->orderBy('name')->get();
        $certificationTools = CertificationTool::query()
            ->where(fn ($query) => $query->notRetired()->orWhereIn('id', $certificate?->requirements()->pluck('certification_tool_id') ?? []))
            ->visibleTo($this->actor())
            ->with(['dimensions.options', 'programs:id'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $globalRoles = CertificationRole::whereNull('certificate_id')->orderBy('sort_order')->get();
        $otherCertificatesQuery = Certificate::query()->visibleTo($this->actor());
        if ($certificate !== null) {
            $otherCertificatesQuery->whereKeyNot($certificate->id);
        }
        $otherCertificates = $otherCertificatesQuery->orderBy('name')->get(['id', 'name']);

        return compact('contactFamilies', 'activityTypes', 'certificationTools', 'globalRoles', 'otherCertificates');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateCertificate(Request $request, ?Certificate $certificate = null): array
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:certificates,name,'.($certificate !== null ? $certificate->id : 'NULL').',id'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'program_scope_mode' => ['required', 'in:all,specific,none'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
            'validity_months' => ['nullable', 'integer', 'min:1'],
            'default_window_months' => ['nullable', 'integer', 'min:1'],
            'prerequisite_mode' => ['required', 'in:all,any'],
            'prerequisite_certificate_ids' => ['nullable', 'array'],
            'prerequisite_certificate_ids.*' => ['distinct', 'exists:certificates,id'],
            'renewal_matches_initial' => ['nullable', 'boolean'],

            'roles' => ['nullable', 'array'],
            'roles.*' => ['array'],
            'roles.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('certification_roles', 'id')->where('certificate_id', $certificate->id ?? 0)],
            'roles.*._delete' => ['nullable', 'boolean'],
            'roles.*.name' => ['nullable', 'string', 'max:255'],
            'roles.*.active' => ['nullable', 'boolean'],

            'requirement_groups' => ['nullable', 'array'],
            'requirement_groups.*' => ['array'],
            'requirement_groups.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('certificate_requirement_groups', 'id')->where('certificate_id', $certificate->id ?? 0)],
            'requirement_groups.*._delete' => ['nullable', 'boolean'],
            'requirement_groups.*.phase' => ['nullable', 'in:'.implode(',', CertificateRequirementPhase::values())],
            'requirement_groups.*.label' => ['nullable', 'string', 'max:255'],
            'requirement_groups.*.satisfy_mode' => ['nullable', 'in:'.implode(',', CertificateGroupSatisfyMode::values())],
            'requirement_groups.*.required_count' => ['nullable', 'integer', 'min:1'],

            'requirements' => ['nullable', 'array'],
            'requirements.*' => ['array'],
            'requirements.*.id' => ['nullable', 'integer', 'distinct', Rule::exists('certificate_requirements', 'id')->where('certificate_id', $certificate->id ?? 0)],
            'requirements.*._delete' => ['nullable', 'boolean'],
            'requirements.*.group_index' => ['nullable', 'integer'],
            'requirements.*.phase' => ['nullable', 'in:'.implode(',', CertificateRequirementPhase::values())],
            'requirements.*.kind' => ['nullable', 'in:'.implode(',', CertificateRequirementKind::values())],
            'requirements.*.contact_family_id' => ['nullable', 'integer', 'exists:contact_families,id'],
            'requirements.*.activity_type_id' => ['nullable', 'integer', 'exists:activity_types,id'],
            'requirements.*.certification_tool_id' => ['nullable', 'integer', 'exists:certification_tools,id'],
            'requirements.*.certification_role_id' => ['nullable', 'integer', Rule::exists('certification_roles', 'id')->where(fn ($query) => $query->whereNull('certificate_id')->orWhere('certificate_id', $certificate->id ?? 0))],
            'requirements.*.role_index' => ['nullable', 'integer'],
            'requirements.*.target_count' => ['nullable', 'integer', 'min:1'],
            'requirements.*.requires_passing' => ['nullable', 'boolean'],
            'requirements.*.threshold_note' => ['nullable', 'string', 'max:255'],
            'requirements.*.window_months' => ['nullable', 'integer', 'min:1'],
            'requirements.*.label' => ['nullable', 'string', 'max:255'],
            'requirements.*.notes' => ['nullable', 'string'],
            'requirements.*.dimension_rules' => ['nullable', 'array'],
            'requirements.*.dimension_rules.*' => ['array'],
            'requirements.*.dimension_rules.*.id' => ['nullable', 'integer', 'distinct'],
            'requirements.*.dimension_rules.*._delete' => ['nullable', 'boolean'],
            'requirements.*.dimension_rules.*.certification_tool_dimension_id' => ['nullable', 'integer', 'exists:certification_tool_dimensions,id'],
            'requirements.*.dimension_rules.*.mode' => ['nullable', 'in:'.implode(',', CertificateDimensionRuleMode::values())],
            'requirements.*.dimension_rules.*.option_ids' => ['nullable', 'array'],
            'requirements.*.dimension_rules.*.option_ids.*' => ['integer', 'exists:certification_tool_dimension_options,id'],
            'requirements.*.dimension_rules.*.min_count' => ['nullable', 'integer', 'min:1'],
        ]);

        $validator->after(function ($validator) use ($request, $certificate) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $mode = $request->input('program_scope_mode', ProgramScopeMode::All->value);
            $projectIds = ProjectProgramScope::normalizeIds($request->input('project_ids', []));
            $programIds = ProjectProgramScope::normalizeIds($request->input('program_ids', []));

            ProjectProgramScope::validateModeSelection($validator, $mode, Certificate::class, $projectIds, $programIds);

            $submittedMode = ProgramScopeMode::tryFrom((string) $mode) ?? ProgramScopeMode::Specific;
            $existingMode = $certificate !== null ? $certificate->program_scope_mode : ProgramScopeMode::None;
            ScopeSync::validateSubmittedMode($validator, $this->actor(), $existingMode, $submittedMode);
            ScopeSync::validateSubmittedProgramsAreInAdminScope(
                $validator,
                $this->actor(),
                $programIds,
                $certificate !== null && $certificate->exists ? $certificate->programs()->pluck('programs.id')->all() : [],
            );

            $this->validateRoles($validator, $request, $certificate);
            $this->validatePrerequisites($validator, $request, $certificate);
            $this->validateRequirementGroupsAndRows($validator, $request);
        });

        $validated = $validator->validate();

        $validated['active'] = $request->boolean('active');
        $validated['renewal_matches_initial'] = $request->boolean('renewal_matches_initial');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, Certificate::class)->value;

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: array<int|string, mixed>, 1: array<int|string, mixed>}
     */
    private function requirementPayloadForSync(array $validated): array
    {
        $groupRows = $validated['requirement_groups'] ?? [];
        $requirementRows = $validated['requirements'] ?? [];

        if (! ($validated['renewal_matches_initial'] ?? false)) {
            return [$groupRows, $requirementRows];
        }

        $renewal = CertificateRequirementPhase::Renewal->value;

        $groupRows = array_filter($groupRows, function ($row) use ($renewal) {
            return is_array($row) && ($row['phase'] ?? CertificateRequirementPhase::Initial->value) !== $renewal;
        });

        $requirementRows = array_filter($requirementRows, function ($row) use ($renewal) {
            return is_array($row) && ($row['phase'] ?? CertificateRequirementPhase::Initial->value) !== $renewal;
        });

        return [$groupRows, $requirementRows];
    }

    private function validatePrerequisites(ValidatorInstance $validator, Request $request, ?Certificate $certificate): void
    {
        $prerequisiteIds = ProjectProgramScope::normalizeIds($request->input('prerequisite_certificate_ids', []));

        if ($prerequisiteIds === []) {
            return;
        }

        if ($certificate && in_array($certificate->id, $prerequisiteIds, true)) {
            $validator->errors()->add('prerequisite_certificate_ids', 'A certificate cannot be a prerequisite of itself.');

            return;
        }

        if (! $certificate) {
            return;
        }

        // A prerequisite cycle exists if any candidate (transitively) already requires this certificate.
        foreach ($prerequisiteIds as $candidateId) {
            if ($this->prerequisiteReaches($candidateId, $certificate->id)) {
                $validator->errors()->add('prerequisite_certificate_ids', 'That selection would create a prerequisite cycle.');

                return;
            }
        }
    }

    /**
     * @param  array<int, int>  $visited
     */
    private function prerequisiteReaches(int $fromCertificateId, int $targetCertificateId, array $visited = []): bool
    {
        if ($fromCertificateId === $targetCertificateId) {
            return true;
        }

        if (in_array($fromCertificateId, $visited, true)) {
            return false;
        }

        $visited[] = $fromCertificateId;

        $nextIds = Certificate::find($fromCertificateId)?->prerequisites()->pluck('certificates.id')->all() ?? [];

        foreach ($nextIds as $nextId) {
            if ($this->prerequisiteReaches((int) $nextId, $targetCertificateId, $visited)) {
                return true;
            }
        }

        return false;
    }

    private function validateRoles(ValidatorInstance $validator, Request $request, ?Certificate $certificate): void
    {
        $seenSlugs = [];

        foreach (($request->input('roles', []) ?? []) as $index => $row) {
            if (! is_array($row) || filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            if (blank($row['name'] ?? null)) {
                continue;
            }

            $slug = Str::slug($row['name']);

            if (isset($seenSlugs[$slug])) {
                $validator->errors()->add("roles.{$index}.name", 'Role names must be unique on this certificate.');

                continue;
            }

            $seenSlugs[$slug] = true;

            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;

            if ($certificate) {
                $conflict = $certificate->roles()
                    ->where('slug', $slug)
                    ->when($rowId, fn ($query) => $query->whereKeyNot($rowId))
                    ->exists();

                if ($conflict) {
                    $validator->errors()->add("roles.{$index}.name", 'Role names must be unique on this certificate.');
                }
            }
        }
    }

    private function validateRequirementGroupsAndRows(ValidatorInstance $validator, Request $request): void
    {
        $groupRows = $request->input('requirement_groups', []) ?? [];
        $requirementRows = $request->input('requirements', []) ?? [];

        $certificateMode = ProjectProgramScope::normalizeMode(
            $request->input('program_scope_mode', ProgramScopeMode::Specific->value),
            Certificate::class,
        );
        $certificateProgramIds = ProjectProgramScope::modeAwareProgramIds(
            $certificateMode,
            Certificate::class,
            ProjectProgramScope::normalizeIds($request->input('project_ids', [])),
            ProjectProgramScope::normalizeIds($request->input('program_ids', [])),
        );

        $groupRequirementCounts = [];

        foreach ($requirementRows as $index => $row) {
            foreach ($row['dimension_rules'] ?? [] as $ruleIndex => $rule) {
                if (! empty($rule['id']) && ! CertificateRequirementDimensionRule::whereKey($rule['id'])->where('certificate_requirement_id', $row['id'] ?? 0)->exists()) {
                    $validator->errors()->add("requirements.{$index}.dimension_rules.{$ruleIndex}.id", 'This rule does not belong to this requirement.');
                }
            }

            if (! is_array($row) || filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            if (blank($row['kind'] ?? null)) {
                $validator->errors()->add("requirements.{$index}.kind", 'Choose what this requirement counts.');

                continue;
            }

            $kind = CertificateRequirementKind::tryFrom($row['kind']);

            if (blank($row['target_count'] ?? null)) {
                $validator->errors()->add("requirements.{$index}.target_count", 'Enter a target count.');
            }

            $roleIndex = $row['role_index'] ?? null;
            $roleRows = $request->input('roles', []) ?? [];
            if ($roleIndex !== null && $roleIndex !== '' && (blank($roleRows[$roleIndex]['name'] ?? null) || filter_var($roleRows[$roleIndex]['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN))) {
                $validator->errors()->add("requirements.{$index}.role_index", 'Choose an available certificate role.');
            }
            if (! empty($row['certification_role_id'])) {
                foreach ($roleRows as $role) {
                    if ((int) ($role['id'] ?? 0) === (int) $row['certification_role_id'] && filter_var($role['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $validator->errors()->add("requirements.{$index}.certification_role_id", 'Choose another role before removing this one.');
                    }
                }
            }

            if ($kind !== CertificateRequirementKind::ToolSubmission) {
                foreach ($row['dimension_rules'] ?? [] as $rule) {
                    if (! filter_var($rule['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                        $validator->errors()->add("requirements.{$index}.kind", 'Remove the submission rules before changing the requirement kind.');
                        break;
                    }
                }
            }

            if ($kind === CertificateRequirementKind::ToolSubmission && blank($row['certification_tool_id'] ?? null)) {
                $validator->errors()->add("requirements.{$index}.certification_tool_id", 'A tool submission requirement must select a tool.');
            }

            $toolId = ! empty($row['certification_tool_id']) ? (int) $row['certification_tool_id'] : null;

            if ($kind === CertificateRequirementKind::ToolSubmission && $toolId) {
                $tool = CertificationTool::query()->with('programs:id')->find($toolId);

                if ($tool instanceof CertificationTool && ! ProjectProgramScope::scopedEntityVisibleToCertificatePrograms(
                    $tool->program_scope_mode,
                    $tool->programs->pluck('id'),
                    $certificateMode,
                    $certificateProgramIds,
                )) {
                    $validator->errors()->add(
                        "requirements.{$index}.certification_tool_id",
                        'The selected tool must be global or share at least one program with this certificate.',
                    );
                }
            }

            foreach ($row['dimension_rules'] ?? [] as $ruleIndex => $rule) {
                if (! is_array($rule) || filter_var($rule['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                if (blank($rule['certification_tool_dimension_id'] ?? null)) {
                    $validator->errors()->add("requirements.{$index}.dimension_rules.{$ruleIndex}.certification_tool_dimension_id", 'Choose a submission dimension.');

                    continue;
                }

                $dimension = CertificationToolDimension::query()->find((int) $rule['certification_tool_dimension_id']);

                if ($dimension instanceof CertificationToolDimension && $toolId && (int) $dimension->certification_tool_id !== $toolId) {
                    $validator->errors()->add("requirements.{$index}.dimension_rules.{$ruleIndex}.certification_tool_dimension_id", 'Dimension must belong to the selected tool.');
                }

                $ruleMode = CertificateDimensionRuleMode::tryFrom($rule['mode'] ?? '');

                if ($ruleMode === null) {
                    $validator->errors()->add("requirements.{$index}.dimension_rules.{$ruleIndex}.mode", 'Choose coverage or a minimum count.');
                }

                if ($ruleMode === CertificateDimensionRuleMode::Quota && blank($rule['min_count'] ?? null)) {
                    $validator->errors()->add("requirements.{$index}.dimension_rules.{$ruleIndex}.min_count", 'Quota rules require a minimum count.');
                }

                if (empty($rule['option_ids'] ?? [])) {
                    $validator->errors()->add("requirements.{$index}.dimension_rules.{$ruleIndex}.option_ids", 'Select at least one category.');
                } elseif ($dimension instanceof CertificationToolDimension && $dimension->options()->whereIn('id', $rule['option_ids'])->count() !== count(array_unique($rule['option_ids']))) {
                    $validator->errors()->add("requirements.{$index}.dimension_rules.{$ruleIndex}.option_ids", 'Categories must belong to the selected dimension.');
                }
            }

            $groupIndex = $row['group_index'] ?? null;
            if ($groupIndex !== null && $groupIndex !== '') {
                $group = $groupRows[$groupIndex] ?? [];
                if (blank($group['label'] ?? null) || filter_var($group['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN) || ($group['phase'] ?? 'initial') !== ($row['phase'] ?? 'initial')) {
                    $validator->errors()->add("requirements.{$index}.group_index", 'Choose a group in the same initial or renewal stage.');
                } else {
                    $groupRequirementCounts[$groupIndex] = ($groupRequirementCounts[$groupIndex] ?? 0) + 1;
                }
            }
        }

        foreach ($groupRows as $index => $row) {
            if (! is_array($row) || filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            if (blank($row['label'] ?? null)) {
                $validator->errors()->add("requirement_groups.{$index}.label", 'Name this requirement group.');
            }

            if (($groupRequirementCounts[$index] ?? 0) === 0) {
                $validator->errors()->add("requirement_groups.{$index}.label", 'Add at least one requirement to this group, or remove it.');
            }

            if (($row['satisfy_mode'] ?? null) !== CertificateGroupSatisfyMode::NOf->value) {
                continue;
            }

            $requiredCount = (int) ($row['required_count'] ?? 0);
            $availableCount = $groupRequirementCounts[$index] ?? 0;

            if ($requiredCount < 1 || $requiredCount > $availableCount) {
                $validator->errors()->add("requirement_groups.{$index}.required_count", 'Required count must be between 1 and the number of requirements in the group.');
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return array<int|string, int>
     */
    private function syncRoles(Certificate $certificate, array $rows): array
    {
        $existing = $certificate->roles()->get()->keyBy('id');
        $retainedIds = collect();
        $sortOrder = 0;
        $indexToId = [];

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $markedForDeletion = filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($markedForDeletion) {
                if ($rowId && $existing->has($rowId)) {
                    $role = $existing->get($rowId);
                    if ($role instanceof CertificationRole) {
                        $role->delete();
                    }
                }

                continue;
            }

            if (blank($row['name'] ?? null)) {
                continue;
            }

            $data = [
                'name' => $row['name'],
                'active' => filter_var($row['active'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => $sortOrder++,
            ];

            if ($rowId && $existing->has($rowId)) {
                $role = $existing->get($rowId);
                if ($role instanceof CertificationRole) {
                    $role->update($data);
                    $retainedIds->push($rowId);
                    $indexToId[$index] = $rowId;
                }
            } else {
                $role = $certificate->roles()->create($data);
                $retainedIds->push($role->id);
                $indexToId[$index] = $role->id;
            }
        }

        $existing->keys()->diff($retainedIds)->each(function (int $id) use ($existing): void {
            $role = $existing->get($id);
            if ($role instanceof CertificationRole) {
                $role->delete();
            }
        });

        return $indexToId;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @return array<int|string, int> map of submitted requirement_groups[] array key -> saved group id
     */
    private function syncRequirementGroups(Certificate $certificate, array $rows): array
    {
        $existing = $certificate->requirementGroups()->get()->keyBy('id');
        $retainedIds = collect();
        $indexToId = [];
        $sortOrder = 0;

        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $markedForDeletion = filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($markedForDeletion) {
                if ($rowId && $existing->has($rowId)) {
                    $group = $existing->get($rowId);
                    if ($group instanceof CertificateRequirementGroup) {
                        $group->delete();
                    }
                }

                continue;
            }

            if (blank($row['label'] ?? null)) {
                continue;
            }

            $satisfyMode = $row['satisfy_mode'] ?? CertificateGroupSatisfyMode::All->value;
            $data = [
                'phase' => $row['phase'] ?? CertificateRequirementPhase::Initial->value,
                'label' => $row['label'],
                'satisfy_mode' => $satisfyMode,
                'required_count' => $satisfyMode === CertificateGroupSatisfyMode::NOf->value ? ($row['required_count'] ?? null) : null,
                'sort_order' => $sortOrder++,
            ];

            if ($rowId && $existing->has($rowId)) {
                $group = $existing->get($rowId);
                if ($group instanceof CertificateRequirementGroup) {
                    $group->update($data);
                } else {
                    continue;
                }
            } else {
                $group = $certificate->requirementGroups()->create($data);
            }

            $retainedIds->push($group->id);
            $indexToId[$index] = $group->id;
        }

        $existing->keys()->diff($retainedIds)->each(function (int $id) use ($existing): void {
            $group = $existing->get($id);
            if ($group instanceof CertificateRequirementGroup) {
                $group->delete();
            }
        });

        return $indexToId;
    }

    /**
     * @param  array<int|string, mixed>  $rows
     * @param  array<int|string, int>  $groupIndexToId
     * @param  array<int|string, int>  $roleIndexToId
     */
    private function syncRequirements(Certificate $certificate, array $rows, array $groupIndexToId, array $roleIndexToId): void
    {
        $existing = $certificate->requirements()->with('dimensionRules')->get()->keyBy('id');
        $retainedIds = collect();
        $sortOrder = 0;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $markedForDeletion = filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($markedForDeletion) {
                if ($rowId && $existing->has($rowId)) {
                    $requirement = $existing->get($rowId);
                    if ($requirement instanceof CertificateRequirement) {
                        $requirement->delete();
                    }
                }

                continue;
            }

            if (blank($row['kind'] ?? null)) {
                continue;
            }

            $kind = CertificateRequirementKind::from($row['kind']);
            $groupIndex = $row['group_index'] ?? null;

            $data = [
                'certificate_requirement_group_id' => ($groupIndex !== null && $groupIndex !== '') ? ($groupIndexToId[$groupIndex] ?? null) : null,
                'phase' => $row['phase'] ?? CertificateRequirementPhase::Initial->value,
                'kind' => $kind->value,
                'contact_family_id' => $kind->usesClassification() ? (($row['contact_family_id'] ?? null) ?: null) : null,
                'activity_type_id' => $kind->usesClassification() ? (($row['activity_type_id'] ?? null) ?: null) : null,
                'certification_tool_id' => $kind->usesTool() ? (($row['certification_tool_id'] ?? null) ?: null) : null,
                'certification_role_id' => filled($row['role_index'] ?? null) ? ($roleIndexToId[$row['role_index']] ?? null) : (($row['certification_role_id'] ?? null) ?: null),
                'target_count' => $row['target_count'] ?? 1,
                'requires_passing' => $kind === CertificateRequirementKind::ToolSubmission
                    ? filter_var($row['requires_passing'] ?? true, FILTER_VALIDATE_BOOLEAN)
                    : true,
                'threshold_note' => $row['threshold_note'] ?? null,
                'window_months' => ($row['window_months'] ?? null) ?: null,
                'label' => $row['label'] ?? null,
                'notes' => $row['notes'] ?? null,
                'sort_order' => $sortOrder++,
            ];

            if ($rowId && $existing->has($rowId)) {
                $requirement = $existing->get($rowId);
                if ($requirement instanceof CertificateRequirement) {
                    $requirement->update($data);
                } else {
                    continue;
                }
            } else {
                $requirement = $certificate->requirements()->create($data);
            }

            $this->syncDimensionRules($requirement, $kind === CertificateRequirementKind::ToolSubmission ? ($row['dimension_rules'] ?? []) : []);
            $retainedIds->push($requirement->id);
        }

        $existing->keys()->diff($retainedIds)->each(function (int $id) use ($existing): void {
            $requirement = $existing->get($id);
            if ($requirement instanceof CertificateRequirement) {
                $requirement->delete();
            }
        });
    }

    /**
     * @param  array<int|string, mixed>  $rows
     */
    private function syncDimensionRules(CertificateRequirement $requirement, array $rows): void
    {
        $existing = $requirement->dimensionRules()->get()->keyBy('id');
        $retainedIds = collect();

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $markedForDeletion = filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if ($markedForDeletion) {
                if ($rowId && $existing->has($rowId)) {
                    $rule = $existing->get($rowId);
                    if ($rule instanceof CertificateRequirementDimensionRule) {
                        $rule->delete();
                    }
                }

                continue;
            }

            if (blank($row['certification_tool_dimension_id'] ?? null)) {
                continue;
            }

            $data = [
                'certification_tool_dimension_id' => $row['certification_tool_dimension_id'],
                'mode' => $row['mode'] ?? CertificateDimensionRuleMode::Coverage->value,
                'option_ids' => array_values(array_unique(array_map('intval', $row['option_ids'] ?? []))),
                'min_count' => ($row['mode'] ?? null) === CertificateDimensionRuleMode::Quota->value ? (($row['min_count'] ?? null) ?: null) : null,
            ];

            if ($rowId && $existing->has($rowId)) {
                $rule = $existing->get($rowId);
                if ($rule instanceof CertificateRequirementDimensionRule) {
                    $rule->update($data);
                    $retainedIds->push($rowId);
                }
            } else {
                $retainedIds->push($requirement->dimensionRules()->create($data)->id);
            }
        }

        $existing->keys()->diff($retainedIds)->each(function (int $id) use ($existing): void {
            $rule = $existing->get($id);
            if ($rule instanceof CertificateRequirementDimensionRule) {
                $rule->delete();
            }
        });
    }
}

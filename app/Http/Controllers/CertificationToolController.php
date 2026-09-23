<?php

namespace App\Http\Controllers;

use App\Enums\CertificationToolScoreUnit;
use App\Enums\ProgramScopeMode;
use App\Models\CertificationTool;
use App\Models\CertificationToolDimension;
use App\Models\CertificationToolDimensionOption;
use App\Models\CertificationToolScoreField;
use App\Models\Program;
use App\Models\Project;
use App\Support\Authorization\ScopeSync;
use App\Support\ProjectProgramScope;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator as ValidatorInstance;

class CertificationToolController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', CertificationTool::class);

        $query = CertificationTool::query()
            ->visibleTo($this->actor())
            ->withCount(['dimensions', 'scoreFields'])
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
                    ->orWhere('certification_tools.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhere('certification_tools.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        match ($sort) {
            'active' => $query->orderBy('certification_tools.active', $direction)->orderBy('certification_tools.name'),
            default => $query->orderBy('certification_tools.sort_order')->orderBy('certification_tools.name', $direction),
        };

        $certificationTools = $query->paginate(20)->withQueryString();

        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);

        if ($request->header('HX-Request')) {
            return view('admin.certification-tools.partials.table', compact('certificationTools', 'sort', 'direction'));
        }

        return view('admin.certification-tools.index', compact(
            'certificationTools',
            'sort',
            'direction',
            'filterProjects',
            'filterPrograms',
        ));
    }

    public function create(): View|RedirectResponse
    {
        $this->authorize('create', CertificationTool::class);

        $projects = ProjectProgramScope::assignableProjectsWithProgramsFor($this->actor());

        return view('admin.certification-tools.create', compact('projects'));
    }

    public function store(Request $request): View|RedirectResponse
    {
        $this->authorize('create', CertificationTool::class);

        $validated = $this->validateTool($request);

        $tool = DB::transaction(function () use ($validated) {
            $tool = CertificationTool::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'active' => $validated['active'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'program_scope_mode' => ProgramScopeMode::Specific,
            ]);

            ScopeSync::applyTo(
                $this->actor(),
                $tool,
                ProgramScopeMode::from($validated['program_scope_mode']),
                $validated['program_ids'] ?? [],
            );

            $this->syncDimensions($tool, $validated['dimensions'] ?? []);
            $this->syncScoreFields($tool, $validated['score_fields'] ?? []);

            return $tool;
        });

        return redirect()
            ->route('certification-tools.edit', $tool)
            ->with('success', 'Certification tool created successfully.');
    }

    public function edit(CertificationTool $certificationTool): View|RedirectResponse
    {
        $this->authorize('update', $certificationTool);

        $projects = ProjectProgramScope::assignableProjectsWithProgramsFor($this->actor(), $certificationTool);
        $certificationTool->load(['programs.projects', 'dimensions.options', 'scoreFields']);

        return view('admin.certification-tools.edit', compact('certificationTool', 'projects'));
    }

    public function update(Request $request, CertificationTool $certificationTool): View|RedirectResponse
    {
        $this->authorize('update', $certificationTool);

        $validated = $this->validateTool($request, $certificationTool);

        DB::transaction(function () use ($validated, $certificationTool) {
            $certificationTool->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'active' => $validated['active'],
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            ScopeSync::applyTo(
                $this->actor(),
                $certificationTool,
                ProgramScopeMode::from($validated['program_scope_mode']),
                $validated['program_ids'] ?? [],
            );

            $this->syncDimensions($certificationTool, $validated['dimensions'] ?? []);
            $this->syncScoreFields($certificationTool, $validated['score_fields'] ?? []);
        });

        return $this->redirectAfterSave($certificationTool, 'Certification tool updated successfully.');
    }

    public function destroy(CertificationTool $certificationTool): View|RedirectResponse
    {
        $this->authorize('delete', $certificationTool);

        if ($certificationTool->requirements()->exists()) {
            $certificationTool->update(['retired_at' => now(), 'active' => false]);

            return redirect()
                ->route('certification-tools.index')
                ->with('success', 'Tool is used by a certificate requirement, so it was retired instead of deleted.');
        }

        $certificationTool->delete();

        return redirect()
            ->route('certification-tools.index')
            ->with('success', 'Certification tool deleted successfully.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTool(Request $request, ?CertificationTool $certificationTool = null): array
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:certification_tools,name,'.($certificationTool !== null ? $certificationTool->id : 'NULL').',id'],
            'description' => ['nullable', 'string'],
            'active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'program_scope_mode' => ['required', 'in:all,specific,none'],
            'project_ids' => ['nullable', 'array'],
            'project_ids.*' => ['distinct', 'exists:projects,id'],
            'program_ids' => ['nullable', 'array'],
            'program_ids.*' => ['distinct', 'exists:programs,id'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.*.id' => ['nullable', 'integer'],
            'dimensions.*._delete' => ['nullable', 'boolean'],
            'dimensions.*.name' => ['nullable', 'string', 'max:255'],
            'dimensions.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'dimensions.*.options' => ['nullable', 'array'],
            'dimensions.*.options.*.id' => ['nullable', 'integer'],
            'dimensions.*.options.*._delete' => ['nullable', 'boolean'],
            'dimensions.*.options.*.label' => ['nullable', 'string', 'max:255'],
            'dimensions.*.options.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'score_fields' => ['nullable', 'array'],
            'score_fields.*.id' => ['nullable', 'integer'],
            'score_fields.*._delete' => ['nullable', 'boolean'],
            'score_fields.*.name' => ['nullable', 'string', 'max:255'],
            'score_fields.*.unit' => ['nullable', 'in:'.implode(',', CertificationToolScoreUnit::values())],
            'score_fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validator->after(function ($validator) use ($request, $certificationTool) {
            $mode = $request->input('program_scope_mode', ProgramScopeMode::All->value);
            $projectIds = ProjectProgramScope::normalizeIds($request->input('project_ids', []));
            $programIds = ProjectProgramScope::normalizeIds($request->input('program_ids', []));

            ProjectProgramScope::validateModeSelection($validator, $mode, CertificationTool::class, $projectIds, $programIds);

            $submittedMode = ProgramScopeMode::tryFrom((string) $mode) ?? ProgramScopeMode::Specific;
            $existingMode = $certificationTool !== null ? $certificationTool->program_scope_mode : ProgramScopeMode::None;
            ScopeSync::validateSubmittedMode($validator, $this->actor(), $existingMode, $submittedMode);
            ScopeSync::validateSubmittedProgramsAreInAdminScope(
                $validator,
                $this->actor(),
                $programIds,
                $certificationTool !== null && $certificationTool->exists ? $certificationTool->programs()->pluck('programs.id')->all() : [],
            );

            $this->validateNestedToolRows($validator, $request, $certificationTool);
        });

        $validated = $validator->validate();

        $validated['active'] = $request->boolean('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, CertificationTool::class)->value;

        return $validated;
    }

    private function validateNestedToolRows(ValidatorInstance $validator, Request $request, ?CertificationTool $tool): void
    {
        $seenDimensionSlugs = [];

        foreach ($request->input('dimensions', []) as $index => $row) {
            if (! is_array($row) || filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            if (blank($row['name'] ?? null)) {
                continue;
            }

            $slug = Str::slug($row['name']);

            if (isset($seenDimensionSlugs[$slug])) {
                $validator->errors()->add("dimensions.{$index}.name", 'Dimension names must be unique on this tool.');

                continue;
            }

            $seenDimensionSlugs[$slug] = true;

            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;

            if ($tool) {
                $conflict = $tool->dimensions()
                    ->where('slug', $slug)
                    ->when($rowId, fn ($query) => $query->whereKeyNot($rowId))
                    ->exists();

                if ($conflict) {
                    $validator->errors()->add("dimensions.{$index}.name", 'Dimension names must be unique on this tool.');
                }
            }

            $seenOptionValues = [];

            foreach ($row['options'] ?? [] as $optionIndex => $option) {
                if (! is_array($option) || filter_var($option['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                if (blank($option['label'] ?? null)) {
                    continue;
                }

                $value = Str::slug($option['label']);

                if (isset($seenOptionValues[$value])) {
                    $validator->errors()->add("dimensions.{$index}.options.{$optionIndex}.label", 'Option labels must be unique within the dimension.');
                }

                $seenOptionValues[$value] = true;
            }
        }

        $seenScoreFieldSlugs = [];

        foreach ($request->input('score_fields', []) as $index => $row) {
            if (! is_array($row) || filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            if (blank($row['name'] ?? null)) {
                continue;
            }

            $slug = Str::slug($row['name']);

            if (isset($seenScoreFieldSlugs[$slug])) {
                $validator->errors()->add("score_fields.{$index}.name", 'Score field names must be unique on this tool.');

                continue;
            }

            $seenScoreFieldSlugs[$slug] = true;

            $rowId = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;

            if ($tool) {
                $conflict = $tool->scoreFields()
                    ->where('slug', $slug)
                    ->when($rowId, fn ($query) => $query->whereKeyNot($rowId))
                    ->exists();

                if ($conflict) {
                    $validator->errors()->add("score_fields.{$index}.name", 'Score field names must be unique on this tool.');
                }
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $rows
     */
    private function syncDimensions(CertificationTool $tool, array $rows): void
    {
        $existing = $tool->dimensions()->with('options')->get()->keyBy('id');
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
                    $dimension = $existing->get($rowId);
                    if ($dimension instanceof CertificationToolDimension) {
                        $dimension->delete();
                    }
                }

                continue;
            }

            if (blank($row['name'] ?? null)) {
                continue;
            }

            $data = [
                'name' => $row['name'],
                'sort_order' => $sortOrder++,
            ];

            if ($rowId && $existing->has($rowId)) {
                $dimension = $existing->get($rowId);
                if ($dimension instanceof CertificationToolDimension) {
                    $dimension->update($data);
                } else {
                    continue;
                }
            } else {
                $dimension = $tool->dimensions()->create($data);
            }

            $this->syncDimensionOptions($dimension, $row['options'] ?? []);
            $retainedIds->push($dimension->id);
        }

        $existing->keys()->diff($retainedIds)->each(function (int $id) use ($existing): void {
            $dimension = $existing->get($id);
            if ($dimension instanceof CertificationToolDimension) {
                $dimension->delete();
            }
        });
    }

    /**
     * @param  array<int|string, mixed>  $rows
     */
    private function syncDimensionOptions(CertificationToolDimension $dimension, array $rows): void
    {
        $existing = $dimension->options()->get()->keyBy('id');
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
                    $option = $existing->get($rowId);
                    if ($option instanceof CertificationToolDimensionOption) {
                        $option->delete();
                    }
                }

                continue;
            }

            if (blank($row['label'] ?? null)) {
                continue;
            }

            $data = [
                'label' => $row['label'],
                'sort_order' => $sortOrder++,
            ];

            if ($rowId && $existing->has($rowId)) {
                $option = $existing->get($rowId);
                if ($option instanceof CertificationToolDimensionOption) {
                    $option->update($data);
                    $retainedIds->push($rowId);
                }
            } else {
                $retainedIds->push($dimension->options()->create($data)->id);
            }
        }

        $existing->keys()->diff($retainedIds)->each(function (int $id) use ($existing): void {
            $option = $existing->get($id);
            if ($option instanceof CertificationToolDimensionOption) {
                $option->delete();
            }
        });
    }

    /**
     * @param  array<int|string, mixed>  $rows
     */
    private function syncScoreFields(CertificationTool $tool, array $rows): void
    {
        $existing = $tool->scoreFields()->get()->keyBy('id');
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
                    $scoreField = $existing->get($rowId);
                    if ($scoreField instanceof CertificationToolScoreField) {
                        $scoreField->delete();
                    }
                }

                continue;
            }

            if (blank($row['name'] ?? null)) {
                continue;
            }

            $data = [
                'name' => $row['name'],
                'unit' => $row['unit'] ?? CertificationToolScoreUnit::Percent->value,
                'sort_order' => $sortOrder++,
            ];

            if ($rowId && $existing->has($rowId)) {
                $scoreField = $existing->get($rowId);
                if ($scoreField instanceof CertificationToolScoreField) {
                    $scoreField->update($data);
                    $retainedIds->push($rowId);
                }
            } else {
                $retainedIds->push($tool->scoreFields()->create($data)->id);
            }
        }

        $existing->keys()->diff($retainedIds)->each(function (int $id) use ($existing): void {
            $scoreField = $existing->get($id);
            if ($scoreField instanceof CertificationToolScoreField) {
                $scoreField->delete();
            }
        });
    }
}

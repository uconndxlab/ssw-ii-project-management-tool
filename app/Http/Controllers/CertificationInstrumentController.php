<?php

namespace App\Http\Controllers;

use App\Enums\CertificationInstrumentDimensionType;
use App\Enums\CertificationInstrumentScoreUnit;
use App\Enums\CertificationInstrumentType;
use App\Enums\ProgramScopeMode;
use App\Models\ActivityType;
use App\Models\CertificationInstrument;
use App\Models\CertificationInstrumentDimension;
use App\Models\CertificationInstrumentDimensionOption;
use App\Models\CertificationInstrumentScoreField;
use App\Models\CertificationInstrumentSubmission;
use App\Models\Program;
use App\Models\Project;
use App\Support\Authorization\ScopeSync;
use App\Support\ProjectProgramScope;
use App\Support\TablePagination;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator as ValidatorInstance;

class CertificationInstrumentController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $this->authorize('viewAny', CertificationInstrument::class);

        $query = CertificationInstrument::query()
            ->visibleTo($this->actor())
            ->withCount(['dimensions', 'scoreFields'])
            ->with(['programs.projects']);

        if ($request->filled('search')) {
            $query->whereLike('name', '%'.$request->input('search').'%');
        }

        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->filled('project_id')) {
            $projectId = (int) $request->input('project_id');
            $query->where(function ($q) use ($projectId) {
                $q->whereHas('programs.projects', fn ($relation) => $relation->where('projects.id', $projectId))
                    ->orWhere('certification_instruments.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        if ($request->filled('program_id')) {
            $programId = (int) $request->input('program_id');
            $query->where(function ($q) use ($programId) {
                $q->whereHas('programs', fn ($relation) => $relation->where('programs.id', $programId))
                    ->orWhere('certification_instruments.program_scope_mode', ProgramScopeMode::All->value);
            });
        }

        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc') === 'desc' ? 'desc' : 'asc';

        match ($sort) {
            'active' => $query->orderBy('certification_instruments.active', $direction)->orderBy('certification_instruments.name'),
            default => $query->orderBy('certification_instruments.sort_order')->orderBy('certification_instruments.name', $direction),
        };

        $certificationInstruments = $query->paginate(TablePagination::perPage($request))->withQueryString();

        $filterProjects = Project::query()->where('active', true)->orderBy('name')->get(['id', 'name']);
        $filterPrograms = Program::query()->where('active', true)->orderBy('name')->get(['id', 'name']);

        if ($request->header('HX-Request')) {
            return view('admin.certification-instruments.partials.table', compact('certificationInstruments', 'sort', 'direction'));
        }

        return view('admin.certification-instruments.index', compact(
            'certificationInstruments',
            'sort',
            'direction',
            'filterProjects',
            'filterPrograms',
        ));
    }

    public function create(): View|RedirectResponse
    {
        $this->authorize('create', CertificationInstrument::class);

        $projects = ProjectProgramScope::assignableProjectsWithProgramsFor($this->actor());
        $activityTypes = $this->activityTypeOptions();

        return view('admin.certification-instruments.create', compact('projects', 'activityTypes'));
    }

    public function store(Request $request): View|RedirectResponse
    {
        $this->authorize('create', CertificationInstrument::class);

        $validated = $this->validateTool($request);

        $tool = DB::transaction(function () use ($validated) {
            $tool = CertificationInstrument::create([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'activity_type_id' => $validated['activity_type_id'],
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
            ->route('certification-instruments.edit', $tool)
            ->with('success', $tool->type->label().' created');
    }

    public function edit(CertificationInstrument $certificationInstrument): View|RedirectResponse
    {
        $this->authorize('update', $certificationInstrument);

        $projects = ProjectProgramScope::assignableProjectsWithProgramsFor($this->actor(), $certificationInstrument);
        $certificationInstrument->load(['programs.projects', 'dimensions.options', 'scoreFields']);
        $usedOptionIds = $this->usedOptionIds($certificationInstrument);
        $typeLocked = $this->isUsed($certificationInstrument);
        $activityTypes = $this->activityTypeOptions($certificationInstrument);

        return view('admin.certification-instruments.edit', compact('certificationInstrument', 'projects', 'usedOptionIds', 'typeLocked', 'activityTypes'));
    }

    public function update(Request $request, CertificationInstrument $certificationInstrument): View|RedirectResponse
    {
        $this->authorize('update', $certificationInstrument);

        $validated = $this->validateTool($request, $certificationInstrument);

        DB::transaction(function () use ($validated, $certificationInstrument) {
            $certificationInstrument->update([
                'name' => $validated['name'],
                'type' => $validated['type'],
                'activity_type_id' => $validated['activity_type_id'],
                'description' => $validated['description'] ?? null,
                'active' => $validated['active'],
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            ScopeSync::applyTo(
                $this->actor(),
                $certificationInstrument,
                ProgramScopeMode::from($validated['program_scope_mode']),
                $validated['program_ids'] ?? [],
            );

            $this->syncDimensions($certificationInstrument, $validated['dimensions'] ?? []);
            $this->syncScoreFields($certificationInstrument, $validated['score_fields'] ?? []);
        });

        return $this->redirectAfterSave($certificationInstrument, $certificationInstrument->type->label().' updated');
    }

    public function destroy(CertificationInstrument $certificationInstrument): View|RedirectResponse
    {
        $this->authorize('delete', $certificationInstrument);

        $label = strtolower($certificationInstrument->type->label());
        if ($this->isUsed($certificationInstrument)) {
            return redirect()
                ->route('certification-instruments.index')
                ->with('error', "Cannot delete this {$label}. It is used by certificate requirements or recorded submissions. Mark it inactive instead.");
        }

        $certificationInstrument->delete();

        return redirect()
            ->route('certification-instruments.index')
            ->with('success', ucfirst($label).' deleted');
    }

    /** @return array<int> options chosen on recorded submissions; they can't be removed or rescored */
    private function usedOptionIds(CertificationInstrument $instrument): array
    {
        return DB::table('certification_instrument_submission_dimension_values')
            ->whereIn('certification_instrument_dimension_id', $instrument->dimensions()->pluck('id'))
            ->distinct()->pluck('certification_instrument_dimension_option_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return Collection<int, ActivityType> active types grouped by family, plus the training's current one */
    private function activityTypeOptions(?CertificationInstrument $instrument = null): Collection
    {
        return ActivityType::query()->with('contactFamily:id,name')
            ->where(fn ($query) => $query->where('active', true)->orWhere('id', $instrument?->activity_type_id))
            ->orderBy('sort_order')->orderBy('name')->get();
    }

    private function isUsed(CertificationInstrument $instrument): bool
    {
        return $instrument->requirements()->exists() || CertificationInstrumentSubmission::query()->where('certification_instrument_id', $instrument->id)->exists();
    }

    /**
     * The dimension rows to validate and sync. A training's sections become its one sections dimension,
     * so it never keeps choose-one dimensions.
     *
     * @param  array<string, mixed>  $input
     * @return array<int|string, mixed>
     */
    private function dimensionRows(array $input, ?CertificationInstrument $instrument): array
    {
        if (($input['type'] ?? null) !== CertificationInstrumentType::Training->value) {
            return is_array($input['dimensions'] ?? null) ? $input['dimensions'] : [];
        }

        return [[
            'id' => $instrument?->sectionsDimension()?->id,
            'name' => 'Sections',
            'type' => CertificationInstrumentDimensionType::Sections->value,
            'options' => is_array($input['sections'] ?? null) ? $input['sections'] : [],
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTool(Request $request, ?CertificationInstrument $certificationInstrument = null): array
    {
        $request->merge(['type' => $request->input('type') ?: ($certificationInstrument !== null ? $certificationInstrument->type->value : CertificationInstrumentType::Tool->value)]);
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255', 'unique:certification_instruments,name,'.($certificationInstrument !== null ? $certificationInstrument->id : 'NULL').',id'],
            'type' => ['required', 'in:'.implode(',', CertificationInstrumentType::values())],
            'activity_type_id' => ['nullable', 'required_if:type,training', 'integer', 'exists:activity_types,id'],
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
            'sections' => ['nullable', 'array'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*._delete' => ['nullable', 'boolean'],
            'sections.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.scored' => ['nullable', 'boolean'],
            'sections.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'score_fields' => ['nullable', 'array'],
            'score_fields.*.id' => ['nullable', 'integer'],
            'score_fields.*._delete' => ['nullable', 'boolean'],
            'score_fields.*.name' => ['nullable', 'string', 'max:255'],
            'score_fields.*.unit' => ['nullable', 'in:'.implode(',', CertificationInstrumentScoreUnit::values())],
            'score_fields.*.max_points' => ['nullable', 'integer', 'min:1', 'max:999'],
            'score_fields.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validator->after(function ($validator) use ($request, $certificationInstrument) {
            $mode = $request->input('program_scope_mode', ProgramScopeMode::All->value);
            $projectIds = ProjectProgramScope::normalizeIds($request->input('project_ids', []));
            $programIds = ProjectProgramScope::normalizeIds($request->input('program_ids', []));

            ProjectProgramScope::validateModeSelection($validator, $mode, CertificationInstrument::class, $projectIds, $programIds);

            $submittedMode = ProgramScopeMode::tryFrom((string) $mode) ?? ProgramScopeMode::Specific;
            $existingMode = $certificationInstrument !== null ? $certificationInstrument->program_scope_mode : ProgramScopeMode::None;
            ScopeSync::validateSubmittedMode($validator, $this->actor(), $existingMode, $submittedMode);
            ScopeSync::validateSubmittedProgramsAreInAdminScope(
                $validator,
                $this->actor(),
                $programIds,
                $certificationInstrument !== null && $certificationInstrument->exists ? $certificationInstrument->programs()->pluck('programs.id')->all() : [],
            );

            $this->validateNestedToolRows($validator, $request, $certificationInstrument);
            if ($certificationInstrument !== null) {
                if ($request->input('type') !== $certificationInstrument->type->value && $this->isUsed($certificationInstrument)) {
                    $validator->errors()->add('type', 'The type cannot change once this '.strtolower($certificationInstrument->type->label()).' has submissions or certificate requirements.');
                }
                if ($certificationInstrument->activity_type_id !== null && (int) $request->input('activity_type_id') !== $certificationInstrument->activity_type_id && $this->isUsed($certificationInstrument)) {
                    $validator->errors()->add('activity_type_id', 'The activity type cannot change once this training has submissions or certificate requirements.');
                }
                $this->validateUsedDefinitions($validator, $request, $certificationInstrument);
            }
        });

        $validated = $validator->validate();

        $validated['active'] = $request->boolean('active');
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['program_scope_mode'] = ProjectProgramScope::normalizeMode($validated['program_scope_mode'] ?? null, CertificationInstrument::class)->value;
        $validated['dimensions'] = $this->dimensionRows($validated, $certificationInstrument);
        $validated['activity_type_id'] = $validated['type'] === CertificationInstrumentType::Training->value ? (int) $validated['activity_type_id'] : null;

        return $validated;
    }

    private function validateUsedDefinitions(ValidatorInstance $validator, Request $request, CertificationInstrument $tool): void
    {
        $dimensions = collect($this->dimensionRows($request->all(), $tool))->filter(fn ($row) => is_array($row) && ! filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN) && filled($row['name'] ?? null));
        $dimensionIds = $dimensions->pluck('id')->filter()->all();
        $optionIds = $dimensions->flatMap(fn ($row) => collect(is_array($row['options'] ?? null) ? $row['options'] : [])->filter(fn ($option) => is_array($option) && ! filter_var($option['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN) && filled($option['label'] ?? null))->pluck('id'))->filter()->all();
        $usedOptionIds = $this->usedOptionIds($tool);
        $usedOptions = CertificationInstrumentDimensionOption::query()->whereKey($usedOptionIds)->get(['id', 'certification_instrument_dimension_id', 'scored']);
        if ($usedOptions->contains(fn ($option) => ! in_array($option->certification_instrument_dimension_id, $dimensionIds) || ! in_array($option->id, $optionIds))) {
            $validator->errors()->add($tool->isTraining() ? 'sections' : 'dimensions', ($tool->isTraining() ? 'Sections' : 'Dimensions and options').' used by recorded submissions cannot be removed. Retire the '.strtolower($tool->type->label()).' instead.');
        }
        $scoredById = $usedOptions->pluck('scored', 'id');
        foreach ($request->input('sections', []) ?? [] as $index => $row) {
            $id = is_array($row) ? (int) ($row['id'] ?? 0) : 0;
            if ($tool->isTraining() && $scoredById->has($id) && filter_var($row['scored'] ?? false, FILTER_VALIDATE_BOOLEAN) !== (bool) $scoredById->get($id)) {
                $validator->errors()->add("sections.{$index}.scored", 'Pass/Fail scoring cannot change on a section used by recorded submissions.');
            }
        }
        $scoreRows = collect($request->array('score_fields'))->filter(fn ($row) => is_array($row) && ! filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN) && filled($row['name'] ?? null));
        $scoreIds = $scoreRows->pluck('id')->filter()->all();
        $usedScoreIds = DB::table('certification_instrument_submission_scores')->whereIn('certification_instrument_score_field_id', $tool->scoreFields()->pluck('id'))->distinct()->pluck('certification_instrument_score_field_id')->map(fn ($id) => (int) $id);
        if ($usedScoreIds->diff(array_map('intval', $scoreIds))->isNotEmpty()) {
            $validator->errors()->add('score_fields', 'Score fields used by recorded submissions cannot be removed. Retire the '.strtolower($tool->type->label()).' instead.');
        }
        $passFail = CertificationInstrumentScoreUnit::PassFail->value;
        $units = $tool->scoreFields()->get()->mapWithKeys(fn (CertificationInstrumentScoreField $field) => [$field->id => $field->unit->value]);
        foreach ($scoreRows as $index => $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($usedScoreIds->contains($id) && (($row['unit'] ?? null) === $passFail) !== ($units->get($id) === $passFail)) {
                $validator->errors()->add("score_fields.{$index}.unit", 'A score field used by recorded submissions cannot change to or from Pass/Fail.');
            }
        }
        $recordedScores = DB::table('certification_instrument_submission_scores')->whereIn('certification_instrument_score_field_id', $usedScoreIds)->get(['certification_instrument_score_field_id', 'value'])->groupBy('certification_instrument_score_field_id');
        foreach ($scoreRows as $index => $row) {
            $maxPoints = ($row['unit'] ?? null) === CertificationInstrumentScoreUnit::Points->value && filter_var($row['max_points'] ?? null, FILTER_VALIDATE_INT) ? (int) $row['max_points'] : null;
            $outOfRange = $maxPoints === null ? null : $recordedScores->get((int) ($row['id'] ?? 0), collect())->pluck('value')
                ->first(fn ($value) => (float) $value < 0 || (float) $value > $maxPoints || floor((float) $value) !== (float) $value);
            if ($outOfRange !== null) {
                $validator->errors()->add("score_fields.{$index}.max_points", "{$row['name']} can't be out of {$maxPoints}: a recorded submission already scored ".CertificationInstrumentScoreUnit::Points->format((string) $outOfRange).' on it. Use a higher number, or edit that submission first.');
            }
        }
    }

    private function validateNestedToolRows(ValidatorInstance $validator, Request $request, ?CertificationInstrument $tool): void
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

        $seenSectionLabels = [];

        foreach ($request->input('type') === CertificationInstrumentType::Training->value ? ($request->input('sections', []) ?? []) : [] as $index => $row) {
            if (! is_array($row) || filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN) || blank($row['label'] ?? null)) {
                continue;
            }

            $value = Str::slug($row['label']);

            if (isset($seenSectionLabels[$value])) {
                $validator->errors()->add("sections.{$index}.label", 'Section names must be unique on this training.');
            }

            $seenSectionLabels[$value] = true;
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
    private function syncDimensions(CertificationInstrument $tool, array $rows): void
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
                    if ($dimension instanceof CertificationInstrumentDimension) {
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
                'type' => $row['type'] ?? CertificationInstrumentDimensionType::ChooseOne->value,
                'sort_order' => $sortOrder++,
            ];

            if ($rowId && $existing->has($rowId)) {
                $dimension = $existing->get($rowId);
                if ($dimension instanceof CertificationInstrumentDimension) {
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
            if ($dimension instanceof CertificationInstrumentDimension) {
                $dimension->delete();
            }
        });
    }

    /**
     * @param  array<int|string, mixed>  $rows
     */
    private function syncDimensionOptions(CertificationInstrumentDimension $dimension, array $rows): void
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
                    if ($option instanceof CertificationInstrumentDimensionOption) {
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
                'scored' => $dimension->type === CertificationInstrumentDimensionType::Sections && filter_var($row['scored'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'sort_order' => $sortOrder++,
            ];

            if ($rowId && $existing->has($rowId)) {
                $option = $existing->get($rowId);
                if ($option instanceof CertificationInstrumentDimensionOption) {
                    $option->update($data);
                    $retainedIds->push($rowId);
                }
            } else {
                $retainedIds->push($dimension->options()->create($data)->id);
            }
        }

        $existing->keys()->diff($retainedIds)->each(function (int $id) use ($existing): void {
            $option = $existing->get($id);
            if ($option instanceof CertificationInstrumentDimensionOption) {
                $option->delete();
            }
        });
    }

    /**
     * @param  array<int|string, mixed>  $rows
     */
    private function syncScoreFields(CertificationInstrument $tool, array $rows): void
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
                    if ($scoreField instanceof CertificationInstrumentScoreField) {
                        $scoreField->delete();
                    }
                }

                continue;
            }

            if (blank($row['name'] ?? null)) {
                continue;
            }

            $unit = $row['unit'] ?? CertificationInstrumentScoreUnit::Percent->value;
            $data = [
                'name' => $row['name'],
                'unit' => $unit,
                'max_points' => $unit === CertificationInstrumentScoreUnit::Points->value && filled($row['max_points'] ?? null) ? (int) $row['max_points'] : null,
                'sort_order' => $sortOrder++,
            ];

            if ($rowId && $existing->has($rowId)) {
                $scoreField = $existing->get($rowId);
                if ($scoreField instanceof CertificationInstrumentScoreField) {
                    $scoreField->update($data);
                    $retainedIds->push($rowId);
                }
            } else {
                $retainedIds->push($tool->scoreFields()->create($data)->id);
            }
        }

        $existing->keys()->diff($retainedIds)->each(function (int $id) use ($existing): void {
            $scoreField = $existing->get($id);
            if ($scoreField instanceof CertificationInstrumentScoreField) {
                $scoreField->delete();
            }
        });
    }
}

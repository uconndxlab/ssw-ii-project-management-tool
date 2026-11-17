@php
    $isEditMode = isset($certificationInstrument);
    $selectedProjectIds = old('project_ids', $isEditMode ? $certificationInstrument->projects->pluck('id')->toArray() : []);
    $selectedProgramIds = old('program_ids', $isEditMode ? $certificationInstrument->programs->pluck('id')->toArray() : []);
    $scopeId = $isEditMode ? 'certification-instrument-edit-scope' : 'certification-instrument-create-scope';

    $instrumentType = old('type', $isEditMode ? $certificationInstrument->type->value : 'tool');
    $typeLocked = $typeLocked ?? false;
    $usedOptionIds = $usedOptionIds ?? [];
    $sectionsDimension = $isEditMode ? $certificationInstrument->sectionsDimension() : null;
    $sectionRows = old('sections', $sectionsDimension?->options->map(fn ($o) => [
        'id' => $o->id,
        'label' => $o->label,
        'scored' => $o->scored,
    ])->all() ?? []);
    $defaultScored = $sectionRows === [] || collect($sectionRows)->every(fn ($row) => filter_var($row['scored'] ?? false, FILTER_VALIDATE_BOOLEAN));

    $dimensionRows = old('dimensions', $isEditMode && ! $certificationInstrument->isTraining() ? $certificationInstrument->dimensions->map(fn ($d) => [
        'id' => $d->id,
        'name' => $d->name,
        'sort_order' => $d->sort_order,
        'options' => $d->options->map(fn ($o) => [
            'id' => $o->id,
            'label' => $o->label,
            'sort_order' => $o->sort_order,
        ])->all(),
    ])->all() : []);

    $scoreFieldRows = old('score_fields', $isEditMode ? $certificationInstrument->scoreFields->map(fn ($f) => [
        'id' => $f->id,
        'name' => $f->name,
        'unit' => $f->unit,
        'sort_order' => $f->sort_order,
    ])->all() : []);
@endphp

<x-section-card title="Information">
    <x-form-field label="Type" name="type" :required="true" group :help="$typeLocked ? 'The type is locked because this record has submissions or certificate requirements.' : null">
        <div class="form-radio-row" role="radiogroup" aria-label="Type" data-instrument-type>
            @foreach(\App\Enums\CertificationInstrumentType::cases() as $type)
                <x-form-radio-card
                    name="type"
                    :value="$type->value"
                    :label="$type->label()"
                    :description="$type === \App\Enums\CertificationInstrumentType::Tool ? 'Choose-one dimensions such as Phase, plus score fields.' : 'A list of sections. Coaches tick many per submission and record Pass/Fail where scored.'"
                    :checked="$instrumentType === $type->value"
                    :required="true"
                    :disabled="$typeLocked"
                />
            @endforeach
        </div>
        @if($typeLocked)
            <input type="hidden" name="type" value="{{ $instrumentType }}">
        @endif
    </x-form-field>

    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text" class="form-control @error('name') is-invalid @enderror"
               id="name" name="name" value="{{ old('name', $certificationInstrument->name ?? '') }}" required>
    </x-form-field>

    <x-form-field label="Description" for="description" name="description">
        <textarea class="form-control @error('description') is-invalid @enderror"
                  id="description" name="description" rows="3">{{ old('description', $certificationInstrument->description ?? '') }}</textarea>
    </x-form-field>

    <x-project-program-scope-picker
        :scope-id="$scopeId"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $certificationInstrument->program_scope_mode?->value ?? 'specific')"
        :lock-all="$isEditMode && $certificationInstrument->program_scope_mode?->value === 'all'"
        project-empty-selection-label="All projects"
        program-empty-selection-label="All programs"
    />

    <x-form-options class="mt-4">
        <x-form-switch
            name="active"
            label="Active"
            help="Only active tools and trainings can be used on new certificate requirements."
            :checked="old('active', $isEditMode ? $certificationInstrument->active : true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<x-section-card title="Dimensions" class="mt-3" data-instrument-for="tool" :hidden="$instrumentType === 'training'">
    <p class="text-muted small mb-2">Categorical facets a coach records for each submission (e.g. Phase, Review Mode). Leave empty for tools with no dimensions (e.g. CREST).</p>
    <x-repeater-rows
        name="dimensions"
        :rows="$dimensionRows"
        add-label="Add dimension"
        :template="view('admin.certification-instruments.partials.dimension-row', ['row' => [], 'dimIndex' => '__INDEX__'])->render()"
    >
        @foreach($dimensionRows as $dimIndex => $dimension)
            @include('admin.certification-instruments.partials.dimension-row', ['row' => $dimension, 'dimIndex' => $dimIndex])
        @endforeach
    </x-repeater-rows>
</x-section-card>

<x-section-card title="Sections" class="mt-3" data-instrument-for="training" :hidden="$instrumentType !== 'training'">
    <p class="text-muted small mb-2">The parts of this training a candidate completes. A coach ticks every section covered in one submission. Scored sections also need a Pass or Fail.</p>
    @error('sections')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
    <x-form-options class="mb-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <div class="form-check form-switch m-0 ps-0 d-flex align-items-center gap-2">
                <input type="checkbox" class="form-check-input ms-0" role="switch" id="section-scoring-default" @checked($defaultScored) data-section-scoring-default aria-describedby="section-scoring-default-help">
                <label class="form-check-label" for="section-scoring-default">Pass/Fail scoring for new sections</label>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-section-apply-all>Apply to all sections</button>
        </div>
        <div class="form-text" id="section-scoring-default-help">Sections already used by recorded submissions keep their scoring.</div>
        <div class="visually-hidden" role="status" aria-live="polite" data-section-status></div>
    </x-form-options>
    <x-repeater-rows
        name="sections"
        :rows="$sectionRows"
        add-label="Add section"
        :template="view('admin.certification-instruments.partials.section-row', ['row' => [], 'sectionIndex' => '__INDEX__', 'defaultScored' => $defaultScored])->render()"
    >
        @foreach($sectionRows as $sectionIndex => $section)
            @include('admin.certification-instruments.partials.section-row', ['row' => $section, 'sectionIndex' => $sectionIndex, 'usedOptionIds' => $usedOptionIds])
        @endforeach
    </x-repeater-rows>
</x-section-card>

<x-section-card title="Score Fields" class="mt-3">
    <p class="text-muted small mb-2">Recorded on each submission: a number, or Pass/Fail. Never evaluated by the system; a coach judges the outcome directly.</p>
    <x-repeater-rows
        name="score_fields"
        :rows="$scoreFieldRows"
        add-label="Add score field"
        :template="view('admin.certification-instruments.partials.score-field-row', ['row' => [], 'fieldIndex' => '__INDEX__'])->render()"
    >
        @foreach($scoreFieldRows as $fieldIndex => $field)
            @include('admin.certification-instruments.partials.score-field-row', ['row' => $field, 'fieldIndex' => $fieldIndex])
        @endforeach
    </x-repeater-rows>
</x-section-card>

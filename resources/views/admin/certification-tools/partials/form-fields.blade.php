@php
    $isEditMode = isset($certificationTool);
    $selectedProjectIds = old('project_ids', $isEditMode ? $certificationTool->projects->pluck('id')->toArray() : []);
    $selectedProgramIds = old('program_ids', $isEditMode ? $certificationTool->programs->pluck('id')->toArray() : []);
    $scopeId = $isEditMode ? 'certification-tool-edit-scope' : 'certification-tool-create-scope';

    $dimensionRows = old('dimensions', $isEditMode ? $certificationTool->dimensions->map(fn ($d) => [
        'id' => $d->id,
        'name' => $d->name,
        'sort_order' => $d->sort_order,
        'options' => $d->options->map(fn ($o) => [
            'id' => $o->id,
            'label' => $o->label,
            'sort_order' => $o->sort_order,
        ])->all(),
    ])->all() : []);

    $scoreFieldRows = old('score_fields', $isEditMode ? $certificationTool->scoreFields->map(fn ($f) => [
        'id' => $f->id,
        'name' => $f->name,
        'unit' => $f->unit,
        'sort_order' => $f->sort_order,
    ])->all() : []);
@endphp

<x-section-card title="Information">
    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text" class="form-control @error('name') is-invalid @enderror"
               id="name" name="name" value="{{ old('name', $certificationTool->name ?? '') }}" required>
    </x-form-field>

    <x-form-field label="Description" for="description" name="description">
        <textarea class="form-control @error('description') is-invalid @enderror"
                  id="description" name="description" rows="3">{{ old('description', $certificationTool->description ?? '') }}</textarea>
    </x-form-field>

    <x-project-program-scope-picker
        :scope-id="$scopeId"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $certificationTool->program_scope_mode?->value ?? 'specific')"
        :lock-all="$isEditMode && $certificationTool->program_scope_mode?->value === 'all'"
        project-empty-selection-label="All projects"
        program-empty-selection-label="All programs"
    />

    <x-form-options class="mt-4">
        <x-form-switch
            name="active"
            label="Active"
            help="Only active tools can be used on new certificate requirements."
            :checked="old('active', $isEditMode ? $certificationTool->active : true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<x-section-card title="Dimensions" class="mt-3">
    <p class="text-muted small mb-2">Categorical facets a coach records for each submission (e.g. Phase, Review Mode). Leave empty for tools with no dimensions (e.g. CREST).</p>
    <x-repeater-rows
        name="dimensions"
        :rows="$dimensionRows"
        add-label="Add dimension"
        :template="view('admin.certification-tools.partials.dimension-row', ['row' => [], 'dimIndex' => '__INDEX__'])->render()"
    >
        @foreach($dimensionRows as $dimIndex => $dimension)
            @include('admin.certification-tools.partials.dimension-row', ['row' => $dimension, 'dimIndex' => $dimIndex])
        @endforeach
    </x-repeater-rows>
</x-section-card>

<x-section-card title="Score Fields" class="mt-3">
    <p class="text-muted small mb-2">Numeric fields recorded on each submission. Never evaluated by the system - a coach judges pass/fail directly.</p>
    <x-repeater-rows
        name="score_fields"
        :rows="$scoreFieldRows"
        add-label="Add score field"
        :template="view('admin.certification-tools.partials.score-field-row', ['row' => [], 'fieldIndex' => '__INDEX__'])->render()"
    >
        @foreach($scoreFieldRows as $fieldIndex => $field)
            @include('admin.certification-tools.partials.score-field-row', ['row' => $field, 'fieldIndex' => $fieldIndex])
        @endforeach
    </x-repeater-rows>
</x-section-card>

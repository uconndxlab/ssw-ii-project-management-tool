@props([
    'scopeId',
    'projects' => collect(),
    'selectedProjectIds' => [],
    'selectedProgramIds' => [],
    'projectFieldName' => 'project_ids[]',
    'programFieldName' => 'program_ids[]',
    'projectErrorKey' => 'project_ids',
    'programErrorKey' => 'program_ids',
    'projectLabel' => 'Projects',
    'programLabel' => 'Programs',
    'projectPlaceholder' => 'Search projects...',
    'programPlaceholder' => 'Search programs...',
    'disabledPlaceholder' => 'Select at least one project first...',
    'projectHelpText' => null,
    'programHelpText' => null,
    'projectHeight' => '260px',
    'programHeight' => '260px',
    'programBadgeClass' => 'bg-primary',
])

@php
    $selectedProjectIds = collect($selectedProjectIds)->map(fn ($id) => (string) $id)->values()->all();
    $selectedProgramIds = collect($selectedProgramIds)->map(fn ($id) => (string) $id)->values()->all();

    $programOptions = $projects->flatMap(function ($project) use ($programBadgeClass) {
        return $project->programs->map(function ($program) use ($project, $programBadgeClass) {
            return [
                'id' => $program->id,
                'name' => $program->name,
                'context' => $project->name,
                'contextBadgeClass' => $programBadgeClass,
            ];
        });
    })->values();

    $projectProgramMap = $projects->mapWithKeys(function ($project) {
        return [
            (string) $project->id => $project->programs
                ->pluck('id')
                ->map(fn ($id) => (string) $id)
                ->values()
                ->all(),
        ];
    })->all();

    $projectPickerId = $scopeId . '-projects';
    $programPickerId = $scopeId . '-programs';
@endphp

<div data-project-program-scope data-scope-id="{{ $scopeId }}" data-project-program-map='@json($projectProgramMap)'>
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label">{{ $projectLabel }}</label>
            <x-token-picker
                :picker-id="$projectPickerId"
                :name="$projectFieldName"
                :items="$projects"
                :selected-ids="$selectedProjectIds"
                :placeholder="$projectPlaceholder"
                :height="$projectHeight"
            />
            @if($projectHelpText)
                <div class="form-text">{{ $projectHelpText }}</div>
            @endif
            @error($projectErrorKey)
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label">{{ $programLabel }}</label>
            <x-token-picker
                :picker-id="$programPickerId"
                :name="$programFieldName"
                :options="$programOptions"
                :selected-ids="$selectedProgramIds"
                :placeholder="$programPlaceholder"
                :disabled-placeholder="$disabledPlaceholder"
                :disabled="empty($selectedProjectIds)"
                :height="$programHeight"
            />
            @if($programHelpText)
                <div class="form-text">{{ $programHelpText }}</div>
            @endif
            @error($programErrorKey)
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

@once
<script>
(function () {
    function parseJson(value, fallback) {
        try {
            return JSON.parse(value || '');
        } catch (error) {
            return fallback;
        }
    }

    function selectedIds(picker) {
        return Array.from(picker.querySelectorAll('[data-token-inputs] input')).map(function (input) {
            return String(input.value);
        });
    }

    function initializeProjectProgramScope(section) {
        if (section.dataset.projectProgramScopeInitialized === 'true') {
            return;
        }

        const scopeId = section.dataset.scopeId;
        const projectPicker = section.querySelector('#' + scopeId + '-projects');
        const programPicker = section.querySelector('#' + scopeId + '-programs');

        if (!projectPicker || !programPicker) {
            return;
        }

        const projectProgramMap = parseJson(section.dataset.projectProgramMap, {});

        function refreshProgramPicker() {
            const projectIds = selectedIds(projectPicker);
            const allowedProgramIds = [];

            projectIds.forEach(function (projectId) {
                const programIds = Array.isArray(projectProgramMap[projectId]) ? projectProgramMap[projectId] : [];
                programIds.forEach(function (programId) {
                    if (!allowedProgramIds.includes(String(programId))) {
                        allowedProgramIds.push(String(programId));
                    }
                });
            });

            programPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
                detail: {
                    disabled: projectIds.length === 0,
                    placeholder: 'Select at least one project first...',
                },
                bubbles: true,
            }));

            programPicker.dispatchEvent(new CustomEvent('token-picker:restrict', {
                detail: allowedProgramIds,
                bubbles: true,
            }));
        }

        projectPicker.addEventListener('token-picker:change', refreshProgramPicker);
        refreshProgramPicker();
        section.dataset.projectProgramScopeInitialized = 'true';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-project-program-scope]').forEach(function (section) {
            initializeProjectProgramScope(section);
        });
    });

    document.body.addEventListener('htmx:afterSwap', function (event) {
        event.target.querySelectorAll('[data-project-program-scope]').forEach(function (section) {
            initializeProjectProgramScope(section);
        });
    });
})();
</script>
@endonce

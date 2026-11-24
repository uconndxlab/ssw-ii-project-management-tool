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
    'projectPlaceholder' => 'Search projects…',
    'programPlaceholder' => 'Search programs…',
    'projectDisabled' => false,
    'projectDisabledPlaceholder' => 'Select the required upstream filters first…',
    'disabledPlaceholder' => 'Select at least one project first…',
    'projectHelpText' => null,
    'programHelpText' => null,
    'projectHeight' => '220px',
    'programHeight' => '220px',
    'projectEmptySelectionLabel' => null,
    'programEmptySelectionLabel' => null,
    'expandEmptyPrograms' => true,
    'showScopeModeSelector' => false,
    'selectedScopeMode' => null,
    'defaultScopeMode' => 'specific',
    'scopeModeOptions' => ['all' => 'All', 'specific' => 'Specific', 'none' => 'None'],
    'scopeModeFieldName' => 'program_scope_mode',
    'scopeModeErrorKey' => 'program_scope_mode',
    'scopeModeLabel' => 'Program Scope',
    'scopeModeHelpText' => null,
    'lockAll' => false,
])

@php
    use App\Support\EntityBadge;
    use Illuminate\Support\Str;

    $scopeModeOptions = collect($scopeModeOptions)->mapWithKeys(function ($label, $value) {
        return [(string) $value => $label];
    })->all();

    $canAssignAll = auth()->user()?->access()->isSystemAdmin() ?? false;
    $lockAll = filter_var($lockAll, FILTER_VALIDATE_BOOLEAN);

    if (! $canAssignAll) {
        if ($lockAll && array_key_exists('all', $scopeModeOptions)) {
            $scopeModeOptions = ['all' => $scopeModeOptions['all']];
            $defaultScopeMode = 'all';
            $selectedScopeMode = $selectedScopeMode ?? 'all';
        } else {
            unset($scopeModeOptions['all']);
            if ((string) $defaultScopeMode === 'all') {
                $defaultScopeMode = array_key_exists('specific', $scopeModeOptions)
                    ? 'specific'
                    : (array_key_first($scopeModeOptions) ?? 'specific');
            }
        }
    }

    $allowedScopeModes = array_keys($scopeModeOptions);
    $defaultProjectEmptySelectionLabel = $projectEmptySelectionLabel ?? '';
    $defaultProgramEmptySelectionLabel = $programEmptySelectionLabel ?? '';
    $projectContextBadgeClass = EntityBadge::relationClasses('project');
    $projectLabelIsRequired = Str::endsWith($projectLabel, ' *');
    $programLabelIsRequired = Str::endsWith($programLabel, ' *');
    $projectLabelText = $projectLabelIsRequired ? Str::beforeLast($projectLabel, ' *') : $projectLabel;
    $programLabelText = $programLabelIsRequired ? Str::beforeLast($programLabel, ' *') : $programLabel;
@endphp

@php
    $scopePicker = App\Support\ProjectProgramScope::scopePickerViewData(
        collect($projects ?? []),
        $selectedProjectIds,
        $selectedProgramIds,
        $scopeId,
        $projectContextBadgeClass,
    );
@endphp

@php
    $selectedScopeMode = old($scopeModeFieldName, $selectedScopeMode ?? $defaultScopeMode);
    if (!in_array((string) $selectedScopeMode, $allowedScopeModes, true)) {
        $selectedScopeMode = in_array('specific', $allowedScopeModes, true)
            ? 'specific'
            : ($allowedScopeModes[0] ?? $defaultScopeMode);
    }
    $projectEmptySelectionLabel = $showScopeModeSelector && $selectedScopeMode === 'specific'
        ? ''
        : $defaultProjectEmptySelectionLabel;
    $programEmptySelectionLabel = $showScopeModeSelector && $selectedScopeMode === 'specific'
        ? ''
        : $defaultProgramEmptySelectionLabel;
@endphp

<div data-project-program-scope
     data-scope-id="{{ $scopeId }}"
     data-program-field-name="{{ $programFieldName }}"
     data-scope-mode-field-name="{{ $scopeModeFieldName }}"
     data-scope-mode-enabled="{{ $showScopeModeSelector ? 'true' : 'false' }}"
     data-default-scope-mode="{{ $defaultScopeMode }}"
     data-expand-empty-programs="{{ $expandEmptyPrograms ? 'true' : 'false' }}"
    data-project-empty-selection-label="{{ $defaultProjectEmptySelectionLabel }}"
    data-program-empty-selection-label="{{ $defaultProgramEmptySelectionLabel }}"
     data-project-program-map='@json($scopePicker['projectProgramMap'])'
     data-program-project-ids-map='@json($scopePicker['programProjectIdsMap'])'
        data-project-names-map='@json($scopePicker['projectNamesMap'])'
        data-project-disabled-placeholder="{{ $projectDisabledPlaceholder }}">
    @if($showScopeModeSelector)
        <fieldset class="mb-3"@if($scopeModeHelpText) aria-describedby="{{ $scopeId }}-scope-mode-help"@endif>
            <legend class="form-label float-none w-auto p-0 fs-6">{{ $scopeModeLabel }}</legend>
            <div class="d-flex flex-wrap gap-3">
                @foreach ($scopeModeOptions as $value => $label)
                    <div class="form-check">
                        <input class="form-check-input"
                               type="radio"
                               id="{{ $scopeId }}-scope-mode-{{ $value }}"
                               name="{{ $scopeModeFieldName }}"
                               value="{{ $value }}"
                               {{ (string) $selectedScopeMode === (string) $value ? 'checked' : '' }}>
                        <label class="form-check-label" for="{{ $scopeId }}-scope-mode-{{ $value }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
            @if($scopeModeHelpText)
                <div class="form-text" id="{{ $scopeId }}-scope-mode-help">{{ $scopeModeHelpText }}</div>
            @endif
            @error($scopeModeErrorKey)
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </fieldset>
    @endif

    <div class="row g-3" data-scope-picker-fields>
        <div class="col-md-6">
            <label class="form-label{{ $projectLabelIsRequired ? ' required-label' : '' }}" id="{{ $scopeId }}-project-label">{{ $projectLabelText }}</label>
            <x-token-picker
                :picker-id="$scopePicker['projectPickerId']"
                :labelled-by="$scopeId.'-project-label'"
                :name="$projectFieldName"
                :items="$scopePicker['scopeProjects']"
                :selected-ids="$scopePicker['selectedProjectIds']"
                :placeholder="$projectPlaceholder"
                :disabled-placeholder="$projectDisabledPlaceholder"
                :disabled="$projectDisabled"
                :height="$projectHeight"
                :empty-selection-label="$projectEmptySelectionLabel ?? ''"
                entity="project"
            />
            @if($projectHelpText)
                <div class="form-text">{{ $projectHelpText }}</div>
            @endif
            @error($projectErrorKey)
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6">
            <label class="form-label{{ $programLabelIsRequired ? ' required-label' : '' }}" id="{{ $scopeId }}-program-label">{{ $programLabelText }}</label>
            <x-token-picker
                :picker-id="$scopePicker['programPickerId']"
                :labelled-by="$scopeId.'-program-label'"
                :name="$programFieldName"
                :options="$scopePicker['programOptions']"
                :selected-ids="$scopePicker['selectedProgramIds']"
                :placeholder="$programPlaceholder"
                :disabled-placeholder="$disabledPlaceholder"
                :disabled="empty($scopePicker['selectedProjectIds'])"
                :height="$programHeight"
                :empty-selection-label="$programEmptySelectionLabel ?? ''"
                entity="program"
            />
            @if($programHelpText)
                <div class="form-text">{{ $programHelpText }}</div>
            @endif
            @error($programErrorKey)
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div data-effective-program-inputs></div>
</div>

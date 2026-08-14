@php
    use App\Enums\ProgramScopeMode;
    use App\Enums\AgreementTimeTrackingRequirement;

    $agreement = $agreement ?? null;
    $agreementLoggingFieldCollection = $agreement?->agreementLoggingFields ?? collect();
    $currentTimeTrackingMode = old('time_tracking_mode', $agreement?->time_tracking_mode?->value ?? 'none');
    $timeTrackingRequirements = collect(AgreementTimeTrackingRequirement::options());
    $selectedProjectIds = old(
        'project_ids',
        $agreement?->projects?->pluck('id')->values()->all() ?? []
    );
    $selectedProgramIds = old('program_ids', $agreement?->programs?->pluck('id')->toArray() ?? []);
    $selectedProgramScopeMode = old('program_scope_mode', $agreement?->program_scope_mode?->value ?? 'specific');
    $allActiveProgramIds = collect($projects ?? [])
        ->flatMap(fn ($project) => $project->programs ?? collect())
        ->pluck('id')
        ->map(fn ($id) => (string) $id)
        ->unique()
        ->values()
        ->all();
    $effectiveSelectedProgramIds = $selectedProgramScopeMode === ProgramScopeMode::All->value
        ? $allActiveProgramIds
        : array_values(array_map('strval', $selectedProgramIds));

    $selectedStateIds = old('state_ids', $agreement?->states?->pluck('id')->toArray() ?? []);
    $selectedAgreementLoggingFieldIds = old('agreement_logging_field_ids', $agreementLoggingFieldCollection->pluck('id')->toArray());
    $requiredAgreementLoggingFieldIds = old(
        'required_agreement_logging_field_ids',
        $agreementLoggingFieldCollection->filter(fn ($field) => $field->pivot->is_required)->pluck('id')->toArray()
    );

    $rawCertificationCandidateRows = old('certification_candidates');
    $certificationCandidateRows = [];

    if (is_array($rawCertificationCandidateRows)) {
        foreach ($rawCertificationCandidateRows as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowKey = is_string($key) ? $key : 'row-' . $key;
            $certificationCandidateRows[] = [
                'row_key' => $rowKey,
                'id' => $row['id'] ?? '',
                'value' => $row['value'] ?? '',
                '_delete' => !empty($row['_delete']) ? 1 : 0,
            ];
        }
    } elseif ($agreement?->certificationCandidates) {
        foreach ($agreement->certificationCandidates as $candidate) {
            $certificationCandidateRows[] = [
                'row_key' => 'existing-' . $candidate->id,
                'id' => $candidate->id,
                'value' => $candidate->name,
                '_delete' => 0,
            ];
        }
    }

    $organizationProgramMap = $organizations->mapWithKeys(fn ($organization) => [
        (string) $organization->id => $organization->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
    ])->all();
    $organizationProgramScopeModeMap = $organizations->mapWithKeys(fn ($organization) => [
        (string) $organization->id => $organization->program_scope_mode?->value ?? 'specific',
    ])->all();

    $organizationStateMap = $organizations->mapWithKeys(fn ($organization) => [
        (string) $organization->id => $organization->states->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
    ])->all();

    $userProgramMap = $users->mapWithKeys(fn ($user) => [
        (string) $user->id => $user->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
    ])->all();
    $userProgramScopeModeMap = $users->mapWithKeys(fn ($user) => [
        (string) $user->id => $user->program_scope_mode?->value ?? 'specific',
    ])->all();

    $teamProgramMap = $teams->mapWithKeys(fn ($team) => [
        (string) $team->id => $team->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
    ])->all();
    $teamProgramScopeModeMap = $teams->mapWithKeys(fn ($team) => [
        (string) $team->id => $team->program_scope_mode?->value ?? 'specific',
    ])->all();

    $loggingFieldProgramMap = $agreementLoggingFields->mapWithKeys(fn ($field) => [
        (string) $field->id => $field->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
    ])->all();
    $loggingFieldProgramScopeModeMap = $agreementLoggingFields->mapWithKeys(fn ($field) => [
        (string) $field->id => $field->program_scope_mode?->value ?? 'all',
    ])->all();
@endphp

<x-section-card title="Information">
    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text"
               class="form-control @error('name') is-invalid @enderror"
               id="name"
               name="name"
               value="{{ old('name', $agreement?->name ?? '') }}"
               required>
    </x-form-field>

    <x-form-field label="Abstract" for="abstract" name="abstract">
        <textarea class="form-control @error('abstract') is-invalid @enderror"
                  id="abstract"
                  name="abstract"
                  rows="4">{{ old('abstract', $agreement?->abstract ?? '') }}</textarea>
    </x-form-field>

    <div class="row">
        <div class="col-md-6">
            <x-form-field label="Start Date" for="start_date" name="start_date">
                <input type="date"
                       class="form-control @error('start_date') is-invalid @enderror"
                       id="start_date"
                       name="start_date"
                       value="{{ old('start_date', $agreement?->start_date?->format('Y-m-d')) }}">
            </x-form-field>
        </div>
        <div class="col-md-6">
            <x-form-field label="End Date" for="end_date" name="end_date">
                <input type="date"
                       class="form-control @error('end_date') is-invalid @enderror"
                       id="end_date"
                       name="end_date"
                       value="{{ old('end_date', $agreement?->end_date?->format('Y-m-d')) }}">
            </x-form-field>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <x-form-field label="Extension Start Date" for="extension_start_date" name="extension_start_date">
                <input type="date"
                       class="form-control @error('extension_start_date') is-invalid @enderror"
                       id="extension_start_date"
                       name="extension_start_date"
                       value="{{ old('extension_start_date', $agreement?->extension_start_date?->format('Y-m-d')) }}">
            </x-form-field>
        </div>
        <div class="col-md-6">
            <x-form-field label="Extension End Date" for="extension_end_date" name="extension_end_date">
                <input type="date"
                       class="form-control @error('extension_end_date') is-invalid @enderror"
                       id="extension_end_date"
                       name="extension_end_date"
                       value="{{ old('extension_end_date', $agreement?->extension_end_date?->format('Y-m-d')) }}">
            </x-form-field>
        </div>
    </div>

    <x-form-field label="Certification Candidates" name="certification_candidates">
        <x-inline-string-list
            list-id="agreement-certification-candidates"
            name="certification_candidates"
            :rows="$certificationCandidateRows"
            :suggestions="$candidateNameSuggestions ?? []"
            add-button-text="Add Candidate"
            empty-message="No candidates added yet."
            input-placeholder="Type a candidate name..."
        />
    </x-form-field>

    <x-form-options>
        <x-form-switch
            name="active"
            label="Active"
            help="Inactive agreements are hidden from lists and activity logging. Assignments and history are kept."
            :checked="old('active', $agreement?->active ?? true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<x-section-card title="Coverage">
    <div class="mb-4">
        <x-project-program-scope-picker
            scope-id="agreement-scope"
            :projects="$projects"
            :selected-project-ids="$selectedProjectIds"
            :selected-program-ids="$selectedProgramIds"
            :show-scope-mode-selector="true"
            :selected-scope-mode="$selectedProgramScopeMode"
            :scope-mode-options="['all' => 'All', 'specific' => 'Specific']"
            project-label="Projects *"
            program-label="Programs *"
            program-help-text="Saved programs control teams, users, logging fields, families, and types below."
            :expand-empty-programs="false"
        />
    </div>

    <x-form-field label="States" name="state_ids" :required="true" help="Organizations are limited to these states and the selected programs." class="mb-0">
        <x-token-picker
            picker-id="agreement-states"
            name="state_ids[]"
            :items="$states"
            :selected-ids="$selectedStateIds"
            placeholder="Search states..."
            :height="'220px'"
            entity="state"
        />
    </x-form-field>
</x-section-card>

<x-section-card title="Organizations">
    @include('agreements.partials.organizations-section', [
        'agreement' => $agreement,
        'organizations' => $organizations,
        'selectedProgramIds' => $effectiveSelectedProgramIds,
        'selectedStateIds' => $selectedStateIds,
        'kfsAccounts' => $kfsAccounts ?? collect(),
    ])
</x-section-card>

<x-section-card title="Time Tracking">
    <div class="d-grid gap-2">
        @foreach ($timeTrackingRequirements as $requirement)
            <x-form-radio-card
                name="time_tracking_mode"
                :value="$requirement['value']"
                :label="$requirement['label']"
                :description="$requirement['description']"
                :checked="$currentTimeTrackingMode === $requirement['value']"
                :required="true"
            />
        @endforeach
    </div>
    @error('time_tracking_mode')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</x-section-card>

@include('agreements.partials.attachments-section', ['agreement' => $agreement])

<x-section-card title="Logging Fields">
    <x-slot:actions>
        <a href="{{ route('logging-fields.index') }}" class="btn btn-sm btn-outline-secondary">Manage Logging Fields</a>
    </x-slot:actions>

    @if($agreementLoggingFields->isEmpty())
        <div class="alert alert-light border mb-0">No agreement logging fields have been defined yet.</div>
    @else
        <x-logging-field-assignment-picker
            :fields="$agreementLoggingFields"
            :selected-field-ids="$selectedAgreementLoggingFieldIds"
            :required-field-ids="$requiredAgreementLoggingFieldIds"
            field-id-input-name="agreement_logging_field_ids"
            required-input-name="required_agreement_logging_field_ids"
            picker-id="agreement-logging-field-picker"
        />
    @endif

    <x-form-subsection class="mt-4" title="Funding Sources" meta="Optional pickers on the activity log.">
        <x-form-switch
            name="require_payor"
            label="Collect Payor Sources"
            help="Agreement organizations linked to KFS accounts on this agreement."
            :checked="filter_var(old('require_payor', $agreement?->require_payor ?? false), FILTER_VALIDATE_BOOLEAN)"
        />

        <x-form-switch
            name="require_payee"
            label="Collect Payee Sources"
            help="Users and organizations with a 6-digit PO number."
            :checked="filter_var(old('require_payee', $agreement?->require_payee ?? false), FILTER_VALIDATE_BOOLEAN)"
            class="mb-0"
        />
    </x-form-subsection>
</x-section-card>

@include('agreements.partials.membership-section', [
    'agreement' => $agreement,
    'teams' => $teams,
    'users' => $users,
    'selectedProgramIds' => $effectiveSelectedProgramIds,
])

@include('agreements.partials.deliverables-section', [
    'agreement' => $agreement,
    'selectedProgramIds' => $effectiveSelectedProgramIds,
])

@once
<script>
(function () {
    function selectedIdsFromPicker(picker) {
        if (!picker) {
            return [];
        }

        return Array.from(picker.querySelectorAll('[data-token-inputs] input')).map(function (input) {
            return String(input.value);
        });
    }

    function normalizeProgramMap(map) {
        return Object.entries(map || {}).reduce(function (carry, entry) {
            carry[String(entry[0])] = Array.isArray(entry[1]) ? entry[1].map(String) : [];
            return carry;
        }, {});
    }

    function normalizeModeMap(map) {
        return Object.entries(map || {}).reduce(function (carry, entry) {
            carry[String(entry[0])] = String(entry[1] || 'specific');
            return carry;
        }, {});
    }

    function matchesProgramScope(programIds, scopeMode, selectedProgramIds, selectedScopeMode, allowGlobal) {
        const normalizedProgramIds = Array.isArray(programIds) ? programIds.map(String) : [];
        const selectedPrograms = new Set((selectedProgramIds || []).map(String));
        const normalizedScopeMode = String(scopeMode || 'specific');
        const normalizedSelectedScopeMode = String(selectedScopeMode || 'specific');

        if (normalizedSelectedScopeMode === 'all') {
            return normalizedScopeMode !== 'none';
        }

        if (normalizedScopeMode === 'all') {
            return true;
        }

        if (normalizedScopeMode === 'none') {
            return false;
        }

        if (normalizedProgramIds.length === 0) {
            return allowGlobal;
        }

        if (selectedPrograms.size === 0) {
            return false;
        }

        return normalizedProgramIds.some(function (programId) {
            return selectedPrograms.has(String(programId));
        });
    }

    function allowedIds(programMap, modeMap, selectedProgramIds, selectedScopeMode, allowGlobal) {
        return Object.entries(programMap || {}).reduce(function (carry, entry) {
            const optionId = String(entry[0]);
            const programIds = Array.isArray(entry[1]) ? entry[1].map(String) : [];
            const scopeMode = modeMap && modeMap[optionId] ? String(modeMap[optionId]) : 'specific';

            if (matchesProgramScope(programIds, scopeMode, selectedProgramIds, selectedScopeMode, allowGlobal)) {
                carry.push(optionId);
            }

            return carry;
        }, []);
    }

    function allowedOrganizationIds(organizationProgramMap, organizationProgramScopeModeMap, organizationStateMap, selectedProgramIds, selectedStateIds, selectedScopeMode) {
        const programAllowed = new Set(allowedIds(organizationProgramMap, organizationProgramScopeModeMap, selectedProgramIds, selectedScopeMode, false));
        const stateAllowed = new Set(Object.entries(organizationStateMap || {}).reduce(function (carry, entry) {
            const optionId = String(entry[0]);
            const stateIds = Array.isArray(entry[1]) ? entry[1].map(String) : [];
            const selectedStates = new Set((selectedStateIds || []).map(String));

            if (stateIds.some(function (stateId) {
                return selectedStates.has(String(stateId));
            })) {
                carry.push(optionId);
            }

            return carry;
        }, []));

        return Array.from(programAllowed).filter(function (organizationId) {
            return stateAllowed.has(String(organizationId));
        });
    }

    function setPickerRestriction(picker, allowedIds, shouldDisable, disabledPlaceholder) {
        if (!picker) {
            return;
        }

        picker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: shouldDisable,
                placeholder: disabledPlaceholder,
            },
            bubbles: true,
        }));

        picker.dispatchEvent(new CustomEvent('token-picker:restrict', {
            detail: allowedIds,
            bubbles: true,
        }));
    }

    function syncLoggingFieldOptions(selectedProgramIds, selectedScopeMode) {
        const selectedPrograms = new Set((selectedProgramIds || []).map(String));

        document.querySelectorAll('[data-agreement-logging-field-option]').forEach(function (option) {
            const programIds = JSON.parse(option.dataset.programIds || '[]').map(String);
            const scopeMode = String(option.dataset.scopeMode || 'specific');
            const visible = matchesProgramScope(programIds, scopeMode, Array.from(selectedPrograms), selectedScopeMode, true);

            option.classList.toggle('d-none', !visible);
            option.querySelectorAll('input').forEach(function (input) {
                if (!visible) {
                    input.checked = false;
                }
                input.disabled = !visible;
            });
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        const programPicker = document.getElementById('agreement-scope-programs');
        const statePicker = document.getElementById('agreement-states');
        const organizationPicker = document.getElementById('agreement-organizations');
        const teamPicker = document.getElementById('agreement-{{ $agreement ? 'edit' : 'create' }}-teams');
        const userPicker = document.getElementById('agreement-{{ $agreement ? 'edit' : 'create' }}-users');
        const membershipSection = document.querySelector('[data-agreement-membership-section]');
        const organizationsSection = document.querySelector('[data-agreement-organizations-section]');

        if (!programPicker) {
            return;
        }

        const allProgramIds = @json($allActiveProgramIds);
        const organizationProgramMap = normalizeProgramMap(@json($organizationProgramMap));
        const organizationProgramScopeModeMap = normalizeModeMap(@json($organizationProgramScopeModeMap));
        const organizationStateMap = normalizeProgramMap(@json($organizationStateMap));
        const teamProgramMap = normalizeProgramMap(@json($teamProgramMap));
        const teamProgramScopeModeMap = normalizeModeMap(@json($teamProgramScopeModeMap));
        const userProgramMap = normalizeProgramMap(@json($userProgramMap));
        const userProgramScopeModeMap = normalizeModeMap(@json($userProgramScopeModeMap));

        function currentSelectedScopeMode() {
            const checked = document.querySelector('input[name="program_scope_mode"]:checked');

            return checked ? String(checked.value) : 'specific';
        }

        function refreshAgreementScope() {
            const selectedScopeMode = currentSelectedScopeMode();
            const selectedProgramIds = selectedScopeMode === 'all'
                ? allProgramIds.slice()
                : selectedIdsFromPicker(programPicker);
            const selectedStateIds = selectedIdsFromPicker(statePicker);
            const hasSelectedPrograms = selectedProgramIds.length > 0;
            const hasSelectedStates = selectedStateIds.length > 0;
            const canSelectOrganizations = hasSelectedPrograms && hasSelectedStates;
            const organizationDisabledPlaceholder = !hasSelectedPrograms && !hasSelectedStates
                ? 'Select at least one program and state first...'
                : (!hasSelectedPrograms
                    ? 'Select at least one program first...'
                    : 'Select at least one state first...');

            setPickerRestriction(
                organizationPicker,
                canSelectOrganizations
                    ? allowedOrganizationIds(
                        organizationProgramMap,
                        organizationProgramScopeModeMap,
                        organizationStateMap,
                        selectedProgramIds,
                        selectedStateIds,
                        selectedScopeMode
                    )
                    : [],
                !canSelectOrganizations,
                organizationDisabledPlaceholder
            );

            setPickerRestriction(
                teamPicker,
                allowedIds(teamProgramMap, teamProgramScopeModeMap, selectedProgramIds, selectedScopeMode, false),
                !hasSelectedPrograms,
                'Select at least one program first...'
            );

            setPickerRestriction(
                userPicker,
                allowedIds(userProgramMap, userProgramScopeModeMap, selectedProgramIds, selectedScopeMode, false),
                !hasSelectedPrograms,
                'Select at least one program first...'
            );

            if (organizationsSection) {
                organizationsSection.dispatchEvent(new CustomEvent('agreement-scope:change', {
                    bubbles: true,
                }));
            }

            if (membershipSection) {
                membershipSection.dataset.programAllowedUserIds = JSON.stringify(allowedIds(userProgramMap, userProgramScopeModeMap, selectedProgramIds, selectedScopeMode, false));
                membershipSection.dispatchEvent(new CustomEvent('agreement-scope:change', {
                    bubbles: true,
                }));
            }

            syncLoggingFieldOptions(selectedProgramIds, selectedScopeMode);

            document.dispatchEvent(new CustomEvent('agreement-scope:change', {
                detail: {
                    selectedProgramIds: selectedProgramIds,
                },
            }));
        }

        programPicker.addEventListener('token-picker:change', refreshAgreementScope);
        document.querySelectorAll('input[name="program_scope_mode"]').forEach(function (input) {
            input.addEventListener('change', refreshAgreementScope);
        });
        if (statePicker) {
            statePicker.addEventListener('token-picker:change', refreshAgreementScope);
        }
        refreshAgreementScope();
    });
})();
</script>
@endonce

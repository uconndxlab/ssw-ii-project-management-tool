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

<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3">Agreement Information</h5>

        <div class="mb-3">
            <label for="name" class="form-label">Agreement Name</label>
            <input type="text"
                   class="form-control @error('name') is-invalid @enderror"
                   id="name"
                   name="name"
                   value="{{ old('name', $agreement?->name ?? '') }}"
                   required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

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
                project-help-text="Required only to filter the program list; projects are not saved on the agreement."
                program-help-text="Select programs explicitly when Specific is selected. These saved programs determine teams, users, logging fields, contact families, and activity types available below. Organizations also require at least one program and one state."
                scope-mode-help-text="Choose whether this agreement applies to all programs or only specific programs."
                :expand-empty-programs="false"
            />
        </div>

        <div class="mb-3">
            <label class="form-label">States <span class="text-danger">*</span></label>
            <x-token-picker
                picker-id="agreement-states"
                name="state_ids[]"
                :items="$states"
                :selected-ids="$selectedStateIds"
                placeholder="Search states..."
                :height="'220px'"
                entity="state"
            />
            <small class="text-muted d-block mt-2">
                Select the states this agreement applies to. Organizations are limited to those linked to the selected states and programs.
            </small>
            @error('state_ids')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        @include('agreements.partials.organizations-section', [
            'agreement' => $agreement,
            'organizations' => $organizations,
            'selectedProgramIds' => $effectiveSelectedProgramIds,
            'selectedStateIds' => $selectedStateIds,
            'kfsAccounts' => $kfsAccounts ?? collect(),
        ])

        <div class="mb-3">
            <label for="abstract" class="form-label">Abstract</label>
            <textarea class="form-control @error('abstract') is-invalid @enderror"
                      id="abstract"
                      name="abstract"
                      rows="4">{{ old('abstract', $agreement?->abstract ?? '') }}</textarea>
            @error('abstract')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date"
                           class="form-control @error('start_date') is-invalid @enderror"
                           id="start_date"
                           name="start_date"
                           value="{{ old('start_date', $agreement?->start_date?->format('Y-m-d')) }}">
                    @error('start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date"
                           class="form-control @error('end_date') is-invalid @enderror"
                           id="end_date"
                           name="end_date"
                           value="{{ old('end_date', $agreement?->end_date?->format('Y-m-d')) }}">
                    @error('end_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="extension_start_date" class="form-label">Extension Start Date</label>
                    <input type="date"
                           class="form-control @error('extension_start_date') is-invalid @enderror"
                           id="extension_start_date"
                           name="extension_start_date"
                           value="{{ old('extension_start_date', $agreement?->extension_start_date?->format('Y-m-d')) }}">
                    @error('extension_start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label for="extension_end_date" class="form-label">Extension End Date</label>
                    <input type="date"
                           class="form-control @error('extension_end_date') is-invalid @enderror"
                           id="extension_end_date"
                           name="extension_end_date"
                           value="{{ old('extension_end_date', $agreement?->extension_end_date?->format('Y-m-d')) }}">
                    @error('extension_end_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="mb-4">
            <div class="mb-2">
                <label class="form-label fw-semibold d-block">Time Tracking Requirements <span class="text-danger">*</span></label>
                <p class="text-muted small mb-0">Choose how time must be tracked for activities covered by this agreement.</p>
            </div>

            <div class="d-grid gap-2">
                @foreach ($timeTrackingRequirements as $requirement)
                    <label class="form-check border rounded px-3 py-2 mb-0 {{ $currentTimeTrackingMode === $requirement['value'] ? 'border-primary bg-light' : '' }}">
                        <input class="form-check-input me-2"
                               type="radio"
                               name="time_tracking_mode"
                               value="{{ $requirement['value'] }}"
                               {{ $currentTimeTrackingMode === $requirement['value'] ? 'checked' : '' }}
                               required>
                        <span class="form-check-label fw-semibold">{{ $requirement['label'] }}</span>
                        <span class="text-muted small d-block">{{ $requirement['description'] }}</span>
                    </label>
                @endforeach
            </div>

            @error('time_tracking_mode')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>

        <div class="col-md-6 mb-3">
            <x-inline-string-list
                list-id="agreement-certification-candidates"
                name="certification_candidates"
                label="Certification Candidates"
                :rows="$certificationCandidateRows"
                :suggestions="$candidateNameSuggestions ?? []"
                add-button-text="Add Candidate"
                empty-message="No certification candidates added yet."
                input-placeholder="Type a candidate name..."
            />
        </div>
    </div>
</div>

@include('agreements.partials.attachments-section', ['agreement' => $agreement])

<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-4">Logging Fields</h5>

        <div class="mb-4 pb-4 border-bottom">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="mb-1">Custom Agreement-Specific Fields</h6>
                    <p class="text-muted small mb-0">These fields will be emphasized when activity is logged against this agreement.</p>
                </div>
                <a href="{{ route('logging-fields.index') }}" class="btn btn-sm btn-outline-secondary">Manage Logging Fields</a>
            </div>

            @if($agreementLoggingFields->isEmpty())
                <div class="alert alert-light border mb-0">No agreement logging fields have been defined yet.</div>
            @else
                <div class="border rounded">
                    @foreach($agreementLoggingFields as $field)
                        @php
                            $fieldProgramIds = $field->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
                        @endphp
                        <label class="d-flex align-items-start gap-3 px-3 py-2 border-bottom {{ $loop->last ? 'border-bottom-0' : '' }}"
                               data-agreement-logging-field-option
                               data-option-id="{{ $field->id }}"
                               data-program-ids='@json($fieldProgramIds)'
                               data-scope-mode="{{ $field->program_scope_mode?->value ?? 'all' }}"
                               data-global="{{ empty($fieldProgramIds) ? 'true' : 'false' }}">
                            <input class="form-check-input mt-1"
                                   type="checkbox"
                                   name="agreement_logging_field_ids[]"
                                   value="{{ $field->id }}"
                                   {{ in_array($field->id, $selectedAgreementLoggingFieldIds) ? 'checked' : '' }}>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $field->name }}</div>
                                <div class="small text-muted">{{ ucfirst($field->field_type) }}{{ $field->help_text ? ' · ' . $field->help_text : '' }}</div>
                            </div>
                            <div class="form-check m-0">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="required_agreement_logging_field_ids[]"
                                       value="{{ $field->id }}"
                                       {{ in_array($field->id, $requiredAgreementLoggingFieldIds) ? 'checked' : '' }}>
                                <label class="form-check-label small">Required</label>
                            </div>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h6 class="mb-1">Additional Fields</h6>
            <p class="text-muted small mb-3">Optionally show separate payor and payee source pickers when activity is logged against this agreement. Payor sources come from agreement organizations linked to KFS accounts on this agreement. Payee sources come from users and organizations with PO numbers.</p>

            <div class="d-grid gap-3">
                <div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="require_payor" value="0">
                        <input type="checkbox"
                               class="form-check-input @error('require_payor') is-invalid @enderror"
                               id="require_payor"
                               name="require_payor"
                               value="1"
                               @checked(filter_var(old('require_payor', $agreement?->require_payor ?? false), FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="require_payor">Collect Payor Sources</label>
                    </div>
                    <div class="form-text">Show an optional payor source field on the activity log, listing agreement organizations that are linked to KFS accounts on this agreement.</div>
                    @error('require_payor')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="require_payee" value="0">
                        <input type="checkbox"
                               class="form-check-input @error('require_payee') is-invalid @enderror"
                               id="require_payee"
                               name="require_payee"
                               value="1"
                               @checked(filter_var(old('require_payee', $agreement?->require_payee ?? false), FILTER_VALIDATE_BOOLEAN))>
                        <label class="form-check-label" for="require_payee">Collect Payee Sources</label>
                    </div>
                    <div class="form-text">Show an optional payee source field on the activity log, listing users and organizations with valid 6-digit PO numbers.</div>
                    @error('require_payee')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>
</div>

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

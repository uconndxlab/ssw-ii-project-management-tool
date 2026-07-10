@php
    use App\Enums\AgreementTimeTrackingRequirement;

    $agreement = $agreement ?? null;
    $agreementLoggingFieldCollection = $agreement?->agreementLoggingFields ?? collect();
    $currentTimeTrackingMode = old('time_tracking_mode', $agreement?->time_tracking_mode?->value ?? 'none');
    $timeTrackingRequirements = collect(AgreementTimeTrackingRequirement::options());
    $selectedProjectIds = old(
        'project_ids',
        $agreement?->projects?->pluck('id')->when(
            ($agreement?->projects?->isEmpty() ?? true) && !empty($agreement?->project_id),
            fn ($collection) => $collection->push($agreement->project_id)
        )->values()->all() ?? []
    );
    $selectedProgramIds = old('program_ids', $agreement?->programs?->pluck('id')->toArray() ?? []);

    $selectedOrganizationIds = old('organization_ids', $agreement?->organizations?->pluck('id')->toArray() ?? []);
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

    $userProgramMap = $users->mapWithKeys(fn ($user) => [
        (string) $user->id => $user->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
    ])->all();

    $teamProgramMap = $teams->mapWithKeys(fn ($team) => [
        (string) $team->id => $team->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
    ])->all();

    $loggingFieldProgramMap = $agreementLoggingFields->mapWithKeys(fn ($field) => [
        (string) $field->id => $field->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all(),
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
                project-help-text="Select the projects this agreement belongs to."
                program-help-text="Programs determine which organizations, teams, users, logging fields, contact families, and activity types are available below."
            />
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Organizations</label>
                    <x-token-picker
                        picker-id="agreement-organizations"
                        name="organization_ids[]"
                        :items="$organizations"
                        :selected-ids="$selectedOrganizationIds"
                        placeholder="Search organizations..."
                        disabled-placeholder="Select at least one program first..."
                        :disabled="empty($selectedProgramIds)"
                        :height="'300px'"
                    />
                    @error('organization_ids')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">States</label>
                    <x-token-picker
                        picker-id="agreement-states"
                        name="state_ids[]"
                        :items="$states"
                        :selected-ids="$selectedStateIds"
                        placeholder="Search states..."
                        :height="'300px'"
                    />
                    @error('state_ids')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

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
        <h5 class="mb-3">Logging Fields</h5>

        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="mb-1">Agreement-Specific Logging Fields</h6>
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
    </div>
</div>

@include('agreements.partials.membership-section', [
    'agreement' => $agreement,
    'teams' => $teams,
    'users' => $users,
])

@include('agreements.partials.deliverables-section', ['agreement' => $agreement])

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

    function allowedIds(programMap, selectedProgramIds, allowGlobal) {
        const selected = new Set((selectedProgramIds || []).map(String));

        return Object.entries(programMap || {}).reduce(function (carry, entry) {
            const optionId = String(entry[0]);
            const programIds = Array.isArray(entry[1]) ? entry[1].map(String) : [];
            const isGlobal = programIds.length === 0;
            const matches = selected.size > 0 && programIds.some(function (programId) {
                return selected.has(String(programId));
            });

            if ((isGlobal && allowGlobal) || matches) {
                carry.push(optionId);
            }

            return carry;
        }, []);
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

    function syncLoggingFieldOptions(selectedProgramIds) {
        const selectedPrograms = new Set((selectedProgramIds || []).map(String));

        document.querySelectorAll('[data-agreement-logging-field-option]').forEach(function (option) {
            const programIds = JSON.parse(option.dataset.programIds || '[]').map(String);
            const isGlobal = option.dataset.global === 'true';
            const visible = isGlobal || (selectedPrograms.size > 0 && programIds.some(function (programId) {
                return selectedPrograms.has(String(programId));
            }));

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
        const organizationPicker = document.getElementById('agreement-organizations');
        const teamPicker = document.getElementById('agreement-{{ $agreement ? 'edit' : 'create' }}-teams');
        const userPicker = document.getElementById('agreement-{{ $agreement ? 'edit' : 'create' }}-users');
        const membershipSection = document.querySelector('[data-agreement-membership-section]');

        if (!programPicker) {
            return;
        }

        const organizationProgramMap = normalizeProgramMap(@json($organizationProgramMap));
        const teamProgramMap = normalizeProgramMap(@json($teamProgramMap));
        const userProgramMap = normalizeProgramMap(@json($userProgramMap));

        function refreshAgreementScope() {
            const selectedProgramIds = selectedIdsFromPicker(programPicker);
            const hasSelectedPrograms = selectedProgramIds.length > 0;

            setPickerRestriction(
                organizationPicker,
                allowedIds(organizationProgramMap, selectedProgramIds, false),
                !hasSelectedPrograms,
                'Select at least one program first...'
            );

            setPickerRestriction(
                teamPicker,
                allowedIds(teamProgramMap, selectedProgramIds, false),
                !hasSelectedPrograms,
                'Select at least one program first...'
            );

            setPickerRestriction(
                userPicker,
                allowedIds(userProgramMap, selectedProgramIds, false),
                !hasSelectedPrograms,
                'Select at least one program first...'
            );

            if (membershipSection) {
                membershipSection.dataset.programAllowedUserIds = JSON.stringify(allowedIds(userProgramMap, selectedProgramIds, false));
                membershipSection.dispatchEvent(new CustomEvent('agreement-scope:change', {
                    bubbles: true,
                }));
            }

            syncLoggingFieldOptions(selectedProgramIds);

            document.dispatchEvent(new CustomEvent('agreement-scope:change', {
                detail: {
                    selectedProgramIds: selectedProgramIds,
                },
            }));
        }

        programPicker.addEventListener('token-picker:change', refreshAgreementScope);
        refreshAgreementScope();
    });
})();
</script>
@endonce

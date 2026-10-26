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

    if (is_array($rawCertificationCandidateRows)) {
        $certificationCandidateRows = collect($rawCertificationCandidateRows)
            ->filter(fn ($row) => is_array($row))
            ->map(function ($row) {
                foreach (['id', '_delete', 'certification_candidate_id', 'name', 'email', 'notes'] as $field) {
                    if (isset($row[$field]) && ! is_scalar($row[$field])) {
                        $row[$field] = '';
                    }
                }
                $row['assignments'] = is_array($row['assignments'] ?? null) ? $row['assignments'] : [];
                $row['assignments'] = collect($row['assignments'])->filter(fn ($assignment) => is_array($assignment))
                    ->map(fn ($assignment) => array_map(fn ($value) => is_scalar($value) || $value === null ? $value : '', $assignment))->all();

                return $row;
            })
            ->all();
    } else {
        $certificationCandidateRows = [];

        foreach ($certificationRosterDisplay ?? [] as $entry) {
            $roster = $entry['roster'];
            $candidate = $roster->candidate;
            $assignments = [];

            foreach ($entry['enrollments'] as $enrollment) {
                $assignments[] = [
                    'enrollment_id' => $enrollment->id,
                    'certificate_id' => $enrollment->certificate_id,
                    'certificate_name' => $enrollment->certificate?->name,
                    'certificate_scope' => $enrollment->certificate?->program_scope_mode->value,
                    'certificate_program_ids' => $enrollment->certificate?->programs->modelKeys() ?? [],
                    'coach_name' => $enrollment->supervisingCoach?->name,
                    'coach_user_id' => $enrollment->supervising_coach_user_id,
                    'can_manage' => (bool) $enrollment->getAttribute('can_manage_assignment'),
                    'can_remove' => $enrollment->isUnstarted() && $enrollment->getAttribute('can_manage_assignment'),
                ];
            }

            $certificationCandidateRows[] = [
                'id' => $roster->id,
                'certification_candidate_id' => $candidate?->id ?? '',
                'name' => $candidate?->name ?? '',
                'email' => $candidate?->email ?? '',
                'notes' => $roster->notes,
                'assignments' => $assignments,
            ];
        }
    }

    foreach ($certificationCandidateRows as &$candidateRow) {
        $knownPerson = $certificationCandidateOptions->firstWhere('id', (int) ($candidateRow['certification_candidate_id'] ?? 0));
        $knownAssignments = collect($knownPerson?->getAttribute('assignment_options') ?? []);
        foreach ($candidateRow['assignments'] as &$assignmentRow) {
            $knownAssignment = $knownAssignments->firstWhere('certificate_id', (int) ($assignmentRow['certificate_id'] ?? 0));
            if ($knownAssignment) {
                $coach = array_key_exists('coach_user_id', $assignmentRow) ? $assignmentRow['coach_user_id'] : $knownAssignment['coach_user_id'];
                $assignmentRow = array_merge($assignmentRow, $knownAssignment, ['coach_user_id' => $coach]);
            }
        }
        unset($assignmentRow);
    }
    unset($candidateRow);

    $certificationPeople = $certificationCandidateOptions->map(fn ($person) => [
        'id' => (string) $person->id, 'name' => $person->name, 'email' => $person->email,
        'active' => $person->active, 'can_edit_identity' => (bool) $person->getAttribute('can_edit_identity'),
        'assignments' => $person->getAttribute('assignment_options') ?? [],
    ])->values()->all();

    $nextCandidateIndex = $certificationCandidateRows === [] ? 0 : max(array_map('intval', array_keys($certificationCandidateRows))) + 1;

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
                  rows="4"
                  maxlength="2000">{{ old('abstract', $agreement?->abstract ?? '') }}</textarea>
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
            :lock-all="$agreement?->exists && $agreement->program_scope_mode?->value === 'all'"
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

<x-section-card title="Certification candidates" id="certification-candidates">
    <p class="form-text mt-0">
        Choose a person, then assign their certificates and optional coach. The same person and
        certificate assignments are reused across agreements.
    </p>
    <x-repeater-rows
        name="certification_candidates"
        :rows="$certificationCandidateRows"
        :next-index="$nextCandidateIndex"
        :sortable="false"
        :scroll="false"
        add-label="Add Candidate"
        :template="view('agreements.partials.certification-candidate-row', [
            'row' => [],
            'candidateIndex' => '__INDEX__',
            'certificationCandidateOptions' => $certificationCandidateOptions,
            'eligibleCertificates' => $eligibleCertificates,
            'eligibleCoaches' => $eligibleCoaches,
        ])->render()"
    >
        @foreach($certificationCandidateRows as $candidateIndex => $candidateRow)
            @include('agreements.partials.certification-candidate-row', [
                'row' => $candidateRow,
                'candidateIndex' => $candidateIndex,
                'certificationCandidateOptions' => $certificationCandidateOptions,
                'eligibleCertificates' => $eligibleCertificates,
                'eligibleCoaches' => $eligibleCoaches,
            ])
        @endforeach
    </x-repeater-rows>
</x-section-card>

<x-section-card title="Time Tracking">
    <div class="d-grid gap-2" role="radiogroup" aria-label="Time tracking"@error('time_tracking_mode') aria-describedby="time-tracking-error"@enderror>
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
        <div class="text-danger small mt-1" id="time-tracking-error">{{ $message }}</div>
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
        @error('agreement_logging_field_ids')
            <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror
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

@once
<script>
function initializeCertificationAssignments() {
    const form = document.querySelector('[data-candidate-row]')?.closest('form')
        || document.querySelector('[data-repeater-name="certification_candidates"]')?.closest('form');
    if (!form || form.dataset.certificationAssignmentsInitialized) return;
    form.dataset.certificationAssignmentsInitialized = 'true';
    const allPrograms = @json($allCertificationProgramIds);
    const people = @json($certificationPeople);
    const ids = (value) => JSON.parse(value || '[]').map(String);
    const intersects = (a, b) => a.some(id => b.includes(String(id)));

    function appliesToPrograms(assignment, mode, programs) {
        return mode !== 'none' && (mode === 'all' || programs.length > 0) && (mode === 'all' || assignment.certificate_scope === 'all'
            || intersects(assignment.certificate_program_ids.map(String), programs));
    }

    function addSavedAssignment(row, assignment) {
        const repeater = row.querySelector('[data-repeater-name$="[assignments]"]');
        const index = Number(repeater.dataset.nextIndex || 0);
        const template = document.createElement('template');
        template.innerHTML = repeater.querySelector(':scope > [data-repeater-template]').innerHTML.replaceAll('__ASSIGN_INDEX__', String(index));
        const item = template.content.firstElementChild;
        item.dataset.existing = '1';
        item.dataset.certificateName = assignment.certificate_name;
        item.dataset.certificateScope = assignment.certificate_scope;
        item.dataset.certificatePrograms = JSON.stringify(assignment.certificate_program_ids);
        item.querySelector('[data-certificate-title]').textContent = assignment.certificate_name;
        const certificateField = item.querySelector('[data-certificate-select]');
        const certificateName = certificateField.name;
        item.querySelector('[data-certificate-field]').remove();
        for (const [field, value] of [['certificate_id', assignment.certificate_id], ['enrollment_id', assignment.enrollment_id]]) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = certificateName.replace('[certificate_id]', `[${field}]`);
            input.value = value;
            item.appendChild(input);
        }
        const coach = item.querySelector('[data-coach-select]');
        const coachId = String(assignment.coach_user_id || '');
        if (coachId && !Array.from(coach.options).some(option => option.value === coachId)) {
            coach.add(new Option(`${assignment.coach_name || 'Current coach'} (currently unavailable)`, coachId));
        }
        coach.value = coach.dataset.originalCoach = coachId;
        const coachHelp = item.querySelector('[data-coach-field] .form-text');
        if (coachHelp) coachHelp.textContent = 'Changing the coach updates this person’s shared assignment.';
        if (!assignment.can_manage) {
            item.querySelector('[data-repeater-remove]').remove();
            const text = document.createElement('p');
            text.className = 'small text-muted mb-0';
            text.textContent = `Coach: ${assignment.coach_name || 'No coach selected'}. This shared assignment cannot be changed here.`;
            item.querySelector('[data-coach-field]').replaceWith(text);
        } else {
            item.querySelector('[data-repeater-remove]').setAttribute('aria-label', `Remove assignment ${assignment.certificate_name}`);
        }
        const list = repeater.querySelector(':scope > [data-repeater-rows]');
        list.appendChild(item);
        list.classList.remove('is-empty');
        repeater.dataset.nextIndex = String(index + 1);
    }

    function refresh() {
        const mode = form.querySelector('[name="program_scope_mode"]:checked')?.value || 'specific';
        const programs = mode === 'all' ? allPrograms.map(String)
            : Array.from(form.querySelectorAll('input[name="program_ids[]"]:not(:disabled)')).map(input => input.value);
        const rosterIds = Array.from(form.querySelectorAll('[data-candidate-row]'))
            .filter(row => row.querySelector('[data-repeater-delete]').value !== '1')
            .map(row => row.querySelector('[data-candidate-select]').value).filter(Boolean);
        form.querySelectorAll('[data-candidate-row]').forEach(row => {
            const removed = row.querySelector('[data-repeater-delete]')?.value === '1';
            const person = row.querySelector('[data-candidate-select]');
            const record = people.find(p => p.id === person.value);
            if (record && !removed) {
                for (const assignment of record.assignments) {
                    const present = Array.from(row.querySelectorAll('[name$="[enrollment_id]"]')).some(input => input.value === String(assignment.enrollment_id));
                    if (!present && appliesToPrograms(assignment, mode, programs)) addSavedAssignment(row, assignment);
                }
            }
            const assignments = Array.from(row.querySelectorAll('[data-assignment-row]'));
            assignments.forEach(assignment => {
                const outOfScope = assignment.dataset.existing === '1' && !appliesToPrograms({
                    certificate_scope: assignment.dataset.certificateScope,
                    certificate_program_ids: ids(assignment.dataset.certificatePrograms),
                }, mode, programs);
                assignment.dataset.scopeHidden = String(outOfScope);
                assignment.hidden = outOfScope;
            });
            const selected = assignments.filter(a => a.querySelector('[data-repeater-delete]').value !== '1'
                && a.dataset.scopeHidden !== 'true' && a.querySelector('[name$="[certificate_id]"]')?.value);
            const assignedIds = assignments.filter(a => a.dataset.existing === '1' || a.querySelector('[data-repeater-delete]').value !== '1')
                .map(a => a.querySelector('[name$="[certificate_id]"]')?.value).filter(Boolean);
            row.querySelector('[data-candidate-assignment-count]').textContent = `${selected.length} certificate${selected.length === 1 ? '' : 's'}`;
            const certificateSummary = row.querySelector('[data-candidate-certificate-summary]');
            certificateSummary.textContent = selected.map(a =>
                a.querySelector('[data-certificate-select]')?.selectedOptions[0]?.textContent.trim() || a.dataset.certificateName
            ).join(' · ');
            certificateSummary.title = certificateSummary.textContent;
            certificateSummary.hidden = selected.length === 0;
            const name = row.querySelector('[data-candidate-name]');
            const email = row.querySelector('[data-candidate-email]');
            row.querySelector('[data-candidate-picker] [data-token-picker]')?.dispatchEvent(new CustomEvent('token-picker:restrict', {
                detail: people.filter(p => p.active && (p.id === person.value || !rosterIds.includes(p.id))).map(p => p.id),
            }));
            const identityEditing = record?.can_edit_identity && row.dataset.identityEditing === '1';
            const creatingPerson = !record && row.dataset.personMode === 'new';
            row.querySelector('[data-person-details]').hidden = !creatingPerson && !identityEditing;
            row.querySelector('[data-candidate-details]').hidden = !record && !creatingPerson;
            if (row.dataset.existing !== '1') {
                row.querySelector('[data-candidate-picker]').hidden = creatingPerson;
                row.querySelector('[data-new-candidate]').hidden = Boolean(record) || creatingPerson;
                row.querySelector('[data-back-to-search]').hidden = !creatingPerson;
            }
            name.readOnly = email.readOnly = Boolean(record && !identityEditing);
            const identityButton = row.querySelector('[data-edit-identity]');
            identityButton.hidden = !record || !record.can_edit_identity || removed;
            identityButton.textContent = identityEditing ? 'Finish person edit' : 'Edit shared person details';
            row.querySelector('[data-person-guidance]').textContent = record
                ? (identityEditing ? 'Changes affect this person on every agreement. A valid email is required.' : 'Person details are shared. Edit assignments below or explicitly change their details.')
                : 'Enter a name and email for a new candidate.';
            const match = !record && email.value.trim() ? people.find(p => p.active && p.email && p.email.toLowerCase() === email.value.trim().toLowerCase()) : null;
            const matchPanel = row.querySelector('[data-email-match]');
            matchPanel.hidden = !match;
            if (match) {
                row.querySelector('[data-email-match-message]').textContent = `This email belongs to ${match.name}. Reuse their existing person record.`;
                row.querySelector('[data-use-email-match]').dataset.candidateId = match.id;
                row.querySelector('[data-use-email-match]').textContent = rosterIds.includes(match.id) ? 'Edit existing candidate' : 'Use existing candidate';
            }
            const creating = creatingPerson;
            const changing = Boolean(record)
                && (name.value !== row.dataset.originalName || email.value !== row.dataset.originalEmail);
            name.required = email.required = Boolean(!removed && (creating || changing));
            row.querySelectorAll('input:not([type="hidden"]),select,textarea').forEach(input => { input.disabled = removed; });

            assignments.forEach(assignment => {
                const certificate = assignment.querySelector('[data-certificate-select]');
                if (certificate) {
                    Array.from(certificate.options).forEach(option => {
                        const allowed = !option.value || (mode !== 'none' && (mode === 'all' || programs.length > 0) &&
                            (mode === 'all' || option.dataset.programScope === 'all' || intersects(ids(option.dataset.programIds), programs))
                            && (option.value === certificate.value || !assignedIds.includes(option.value)));
                        option.hidden = !allowed && option.value !== certificate.value;
                        option.disabled = !allowed && option.value !== certificate.value;
                        option.dataset.allowed = String(allowed);
                    });
                    certificate.setCustomValidity(certificate.value && certificate.selectedOptions[0]?.dataset.allowed === 'false'
                        ? 'Choose a certificate in the agreement’s selected programs.' : '');
                }
                const option = certificate?.selectedOptions[0];
                const certificateScope = option?.dataset.programScope || assignment.dataset.certificateScope;
                const relevant = certificateScope === 'all' ? programs : ids(option?.dataset.programIds || assignment.dataset.certificatePrograms).filter(id => programs.includes(id));
                const coach = assignment.querySelector('[data-coach-select]');
                if (coach) {
                    Array.from(coach.options).forEach(option => {
                        const allowed = !option.value || (intersects(ids(option.dataset.dutyPrograms), relevant)
                            && (option.dataset.canViewAgreement === '1' || intersects(ids(option.dataset.viewPrograms), programs)));
                        option.hidden = !allowed && option.value !== coach.value;
                        option.disabled = !allowed && option.value !== coach.value;
                        option.dataset.allowed = String(allowed);
                    });
                    coach.setCustomValidity(coach.value && coach.value !== coach.dataset.originalCoach
                        && coach.selectedOptions[0]?.dataset.allowed === 'false' ? 'Choose a coach with access and a duty in this certificate’s programs.' : '');
                }
                if (assignment.querySelector('[data-repeater-delete]').value === '1' || assignment.dataset.scopeHidden === 'true' || removed) {
                    assignment.querySelectorAll('select').forEach(select => { select.disabled = true; select.setCustomValidity(''); });
                }
            });
            const assignmentRepeater = row.querySelector('[data-repeater-name$="[assignments]"]');
            const possible = Array.from(assignmentRepeater.querySelector(':scope > [data-repeater-template]').content.querySelector('[data-certificate-select]').options)
                .filter(option => option.value && appliesToPrograms({certificate_scope: option.dataset.programScope, certificate_program_ids: ids(option.dataset.programIds)}, mode, programs));
            const addAssignment = assignmentRepeater.querySelector(':scope > [data-repeater-add]');
            addAssignment.disabled = record?.active === false || !possible.some(option => !assignedIds.includes(option.value));
            addAssignment.querySelector('span').textContent = mode !== 'all' && programs.length === 0
                ? 'Select agreement programs first'
                : (addAssignment.disabled ? 'No other certificates available' : 'Add certificate');
        });
        form.querySelectorAll('[data-certification-edit]').forEach(button => {
            const row = button.closest('[data-repeater-row]');
            const expanded = row.dataset.repeaterMode === 'edit';
            const title = row.querySelector('.repeater-row-title')?.textContent.trim() || 'item';
            button.textContent = expanded ? 'Done' : 'Edit';
            button.setAttribute('aria-expanded', String(expanded));
            button.setAttribute('aria-label', `${expanded ? 'Collapse' : 'Edit'} ${title}`);
        });
    }

    form.addEventListener('change', refresh);
    form.addEventListener('token-picker:change', event => {
        const picker = event.target.closest('[data-candidate-picker] [data-token-picker]');
        if (!picker) { refresh(); return; }
        const row = picker.closest('[data-candidate-row]');
        const identity = row.querySelector('[data-candidate-select]');
        const selectedId = picker.querySelector('[data-token-inputs] input')?.value || '';
        if (identity.value === selectedId) return;
        const hasAssignments = Array.from(row.querySelectorAll('[data-assignment-row]')).some(a =>
            a.querySelector('[data-repeater-delete]').value !== '1' && a.querySelector('[name$="[certificate_id]"]')?.value);
        if (hasAssignments && !window.confirm('Changing the candidate clears the unsaved certificate rows in this editor. Continue?')) {
            picker.dispatchEvent(new CustomEvent('token-picker:set', {detail: identity.value ? [identity.value] : []}));
            return;
        }
        const record = people.find(p => p.id === selectedId);
        identity.value = selectedId;
        row.dataset.originalName = record?.name || '';
        row.dataset.originalEmail = record?.email || '';
        row.dataset.identityEditing = '0';
        row.dataset.personMode = record ? 'existing' : 'search';
        row.querySelector('[data-candidate-name]').value = record?.name || '';
        row.querySelector('[data-candidate-email]').value = record?.email || '';
        row.querySelector('[name$="[notes]"]').value = '';
        row.querySelectorAll('[data-assignment-row]').forEach(assignment => assignment.remove());
        row.querySelector('[data-candidate-name]').dispatchEvent(new Event('input', {bubbles:true}));
        row.querySelector('[data-candidate-email]').dispatchEvent(new Event('input', {bubbles:true}));
        refresh();
    });
    form.addEventListener('click', event => {
        const row = event.target.closest('[data-candidate-row]');
        if (!row) return;
        if (event.target.closest('[data-edit-identity]')) {
            if (row.dataset.identityEditing === '1') {
                const name = row.querySelector('[data-candidate-name]');
                const email = row.querySelector('[data-candidate-email]');
                if (!name.reportValidity() || !email.reportValidity()) return;
            }
            row.dataset.identityEditing = row.dataset.identityEditing === '1' ? '0' : '1';
            refresh();
            if (row.dataset.identityEditing === '1') row.querySelector('[data-candidate-name]').focus();
        }
        const matchButton = event.target.closest('[data-use-email-match]');
        if (matchButton) {
            const existingRow = Array.from(form.querySelectorAll('[data-candidate-row]')).find(other =>
                other !== row && other.querySelector('[data-repeater-delete]').value !== '1'
                && other.querySelector('[data-candidate-select]').value === matchButton.dataset.candidateId);
            if (existingRow) {
                const hasAssignments = Array.from(row.querySelectorAll('[data-assignment-row]')).some(a => a.querySelector('[name$="[certificate_id]"]')?.value);
                if (hasAssignments && !window.confirm('Discard the unsaved certificate rows here and edit the existing roster candidate?')) return;
                row.querySelector('[data-repeater-remove]').click();
                setTimeout(() => {
                    form.querySelectorAll('[data-candidate-row]').forEach(other => { other.dataset.repeaterMode = other === existingRow ? 'edit' : 'display'; });
                    refresh();
                    existingRow.querySelector('[data-repeater-add]')?.focus();
                }, 0);
            } else {
                row.querySelector('[data-candidate-picker] [data-token-picker]').dispatchEvent(new CustomEvent('token-picker:set', {detail:[matchButton.dataset.candidateId]}));
            }
        }
        if (event.target.closest('[data-new-candidate]')) {
            const picker = row.querySelector('[data-candidate-picker] [data-token-picker]');
            const term = picker.querySelector('[data-token-search]').value.trim();
            picker.dispatchEvent(new CustomEvent('token-picker:set', {detail:[]}));
            if (row.querySelector('[data-candidate-select]').value === '') {
                row.dataset.personMode = 'new';
                refresh();
                const field = row.querySelector(term.includes('@') ? '[data-candidate-email]' : '[data-candidate-name]');
                if (!field.value) field.value = term;
                field.dispatchEvent(new Event('input', {bubbles:true}));
                field.focus();
            }
        }
        if (event.target.closest('[data-back-to-search]')) {
            const hasContent = row.querySelector('[data-candidate-name]').value || row.querySelector('[data-candidate-email]').value
                || row.querySelector('[data-assignment-row]');
            if (hasContent && !window.confirm('Discard this new candidate’s unsaved details and return to search?')) return;
            row.dataset.personMode = 'search';
            row.querySelector('[data-candidate-name]').value = '';
            row.querySelector('[data-candidate-email]').value = '';
            row.querySelector('[name$="[notes]"]').value = '';
            row.querySelectorAll('[data-assignment-row]').forEach(assignment => assignment.remove());
            refresh();
            row.querySelector('[data-token-search]').focus();
        }
    });
    form.addEventListener('input', refresh);
    form.addEventListener('project-program-scope:change', refresh);
    form.addEventListener('invalid', event => {
        const candidate = event.target.closest('[data-candidate-row]');
        if (event.target.matches('[data-candidate-name],[data-candidate-email]') && candidate.querySelector('[data-candidate-select]').value) {
            candidate.dataset.identityEditing = '1';
        }
        event.target.closest('details')?.setAttribute('open', '');
        let row = event.target.closest('[data-repeater-row]');
        while (row) {
            row.dataset.repeaterMode = 'edit';
            row = row.parentElement.closest('[data-repeater-row]');
        }
        refresh();
    }, true);
    document.addEventListener('click', event => {
        const button = event.target.closest('[data-repeater-add],[data-repeater-remove],[data-row-edit],[data-row-display-click]');
        if (!button || !form.contains(button) || !button.closest('[data-repeater-name="certification_candidates"]')) return;
        const removing = button.hasAttribute('data-repeater-remove');
        const row = button.closest('[data-repeater-row]');
        if (removing && row.dataset.existing === '1' && button.dataset.confirmRemove && !window.confirm(button.dataset.confirmRemove)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }
        const addButton = button.closest('[data-repeater]')?.querySelector(':scope > [data-repeater-add]');
        setTimeout(() => {
            const focusedRow = button.hasAttribute('data-repeater-add')
                ? button.closest('[data-repeater]').querySelector(':scope > [data-repeater-rows]')?.lastElementChild
                : row;
            if (!removing && focusedRow?.dataset.repeaterMode === 'edit') {
                Array.from(focusedRow.parentElement.children).forEach(sibling => {
                    if (sibling !== focusedRow && sibling.hasAttribute('data-repeater-row')) sibling.dataset.repeaterMode = 'display';
                });
            }
            refresh();
            if (removing || button.hasAttribute('data-repeater-add')) form.dispatchEvent(new Event('input', {bubbles:true}));
            if (removing) {
                addButton?.focus();
                const live = document.getElementById('app-live');
                if (live) live.textContent = 'Removed from this form. Save changes to apply.';
            }
        }, 0);
    }, true);
    const linkedRow = document.getElementById(location.hash.slice(1));
    if (linkedRow?.matches('[data-candidate-row]')) {
        form.querySelectorAll('[data-candidate-row]').forEach(row => { row.dataset.repeaterMode = row === linkedRow ? 'edit' : 'display'; });
        setTimeout(() => linkedRow.scrollIntoView({block:'center'}), 0);
    }
    refresh();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeCertificationAssignments);
} else {
    initializeCertificationAssignments();
}
</script>
@endonce

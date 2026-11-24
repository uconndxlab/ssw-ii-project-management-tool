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
                foreach (['id', '_delete', 'certification_candidate_id', 'name', 'email', 'start_date', 'notes'] as $field) {
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
                    'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
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
                'start_date' => $candidate?->start_date?->format('Y-m-d') ?? '',
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
                $assignmentRow = array_merge($assignmentRow, $knownAssignment, ['coach_user_id' => $coach, 'enrolled_at' => $assignmentRow['enrolled_at'] ?? $knownAssignment['enrolled_at'] ?? '']);
            }
        }
        unset($assignmentRow);
    }
    unset($candidateRow);

    $certificationPeople = $certificationCandidateOptions->map(fn ($person) => [
        'id' => (string) $person->id, 'name' => $person->name, 'email' => $person->email,
        'start_date' => $person->start_date?->format('Y-m-d') ?? '',
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

<x-section-card title="Coverage" data-agreement-scope>
    @php
        $agreementScopeConfig = [
            'allActiveProgramIds' => $allActiveProgramIds,
            'organizationProgramMap' => $organizationProgramMap,
            'organizationProgramScopeModeMap' => $organizationProgramScopeModeMap,
            'organizationStateMap' => $organizationStateMap,
            'teamProgramMap' => $teamProgramMap,
            'teamProgramScopeModeMap' => $teamProgramScopeModeMap,
            'userProgramMap' => $userProgramMap,
            'userProgramScopeModeMap' => $userProgramScopeModeMap,
        ];
    @endphp
    <script type="application/json" data-agreement-scope-config>@json($agreementScopeConfig)</script>

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
            placeholder="Search states…"
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
    @php
        $agreementCertificationConfig = [
            'allPrograms' => $allCertificationProgramIds,
            'people' => $certificationPeople,
        ];
    @endphp
    <script type="application/json" data-agreement-certification-config>@json($agreementCertificationConfig)</script>

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
        <p class="text-muted small mb-0">No agreement logging fields have been defined yet.</p>
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
    @push('scripts')
        @vite('resources/js/agreement-form.js')
    @endpush
@endonce

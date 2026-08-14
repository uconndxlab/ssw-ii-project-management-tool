@php
    $row = $row ?? [];
    $fieldPrefix = $fieldPrefix ?? 'deliverable_editor';
    $classificationLocked = !empty($row['classification_locked']);
    $semanticLocked = !empty($row['semantic_locked']);

    $contactFamilyLabel = $contactFamilies->firstWhere('id', $row['contact_family_id'] ?? null)?->name ?? '—';
    $activityTypeLabel = $activityTypes->firstWhere('id', $row['activity_type_id'] ?? null)?->name ?? 'Any activity type';
    $programLabel = collect($projects ?? [])->flatMap(fn ($p) => $p->programs ?? collect())
        ->merge($agreement?->programs ?? collect())
        ->firstWhere('id', $row['program_id'] ?? null)?->name ?? 'Any selected agreement program';

    $metricOptions = ['time' => 'Time', 'completion' => 'Completion'];
    $basisOptions = [
        'contact' => [
            'label' => 'By Contact',
            'description' => 'Count at the contact. No assignees.',
        ],
        'user' => [
            'label' => 'By User',
            'description' => 'Attribute to users or teams.',
        ],
    ];
    $groupingOptions = [
        'joint' => [
            'label' => 'Joint',
            'description' => 'One shared target.',
        ],
        'individual' => [
            'label' => 'Individual',
            'description' => 'One target per assigned user.',
        ],
    ];

    $selectedContactFamily = $contactFamilies->firstWhere('id', $row['contact_family_id'] ?? null);
    $timeBasisOptions = [
        'observed' => [
            'label' => 'Observed',
            'description' => 'Hours logged on the activity.',
        ],
        'allotted' => [
            'label' => 'Allotted',
            'description' => 'Duration from the activity type.',
        ],
    ];

    $currentTimeBasis = ($row['metric_type'] ?? '') === 'time'
        ? ($row['time_basis'] ?? 'observed')
        : 'observed';

    $agreementProgramIds = collect($agreement?->programs ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all();
    $activityTypesInScope = \App\Support\ActivityTypeDuration::filterActivityTypesInScope(
        collect($activityTypes ?? []),
        !empty($row['contact_family_id']) ? (int) $row['contact_family_id'] : null,
        !empty($row['activity_type_id']) ? (int) $row['activity_type_id'] : null,
        $agreementProgramIds
    );
    $selectionSupportsAllotted = \App\Support\ActivityTypeDuration::selectionSupportsAllottedTime(
        !empty($row['contact_family_id']) ? (int) $row['contact_family_id'] : null,
        !empty($row['activity_type_id']) ? (int) $row['activity_type_id'] : null,
        $activityTypesInScope
    );
    $allottedUnits = \App\Support\ActivityTypeDuration::resolveAllottedUnitsForSelection(
        !empty($row['contact_family_id']) ? (int) $row['contact_family_id'] : null,
        !empty($row['activity_type_id']) ? (int) $row['activity_type_id'] : null,
        $activityTypesInScope
    );
    $currentAllottedTimeUnit = $row['allotted_time_unit'] ?? null;
    if (($row['metric_type'] ?? '') === 'time' && $currentTimeBasis === 'allotted' && !$currentAllottedTimeUnit && count($allottedUnits['allowed_units']) === 1) {
        $currentAllottedTimeUnit = $allottedUnits['allowed_units'][0];
    }
    if (($row['metric_type'] ?? '') === 'time' && $currentTimeBasis === 'observed') {
        $currentAllottedTimeUnit = \App\Support\ActivityTypeDuration::UNIT_HOURS;
    }
    $targetUnitLabel = ($row['metric_type'] ?? '') === 'completion'
        ? 'Target Completions'
        : 'Target';
    $showTargetUnitPicker = ($row['metric_type'] ?? '') === 'time';
    $showAllottedWarning = ($row['metric_type'] ?? '') === 'time' && !$selectionSupportsAllotted;
    $showAdditionalTime = ($row['metric_type'] ?? '') === 'time'
        && $currentTimeBasis === 'observed'
        && !empty($row['contact_family_id'])
        && $selectedContactFamily?->track_additional_time;
@endphp

<div class="deliverable-fields form-subsections" data-deliverable-fields>
    <x-form-subsection title="Activity">
        <div class="alert alert-warning small py-2 {{ $classificationLocked ? '' : 'd-none' }}" data-deliverable-classification-lock-notice>
            Family, type, and program are locked. Duplicate this deliverable to change them.
        </div>

        <div class="{{ $classificationLocked ? 'd-none' : '' }}" data-deliverable-classification-editor>
            <x-form-field label="Activity Family" required>
                <select class="form-select" name="{{ $fieldPrefix }}[contact_family_id]" data-deliverable-contact-family>
                    <option value="">Select activity family...</option>
                    @foreach($contactFamilies as $family)
                        @php
                            $familyProgramIds = $family->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
                        @endphp
                        <option value="{{ $family->id }}"
                                data-program-ids='@json($familyProgramIds)'
                                data-scope-mode="{{ $family->program_scope_mode?->value ?? 'all' }}"
                                data-global="{{ empty($familyProgramIds) ? 'true' : 'false' }}"
                                data-track-additional-time="{{ $family->track_additional_time ? '1' : '0' }}"
                                @selected((string) ($row['contact_family_id'] ?? '') === (string) $family->id)>
                            {{ $family->name }}
                        </option>
                    @endforeach
                </select>
            </x-form-field>

            <x-form-field label="Activity Type">
                <select class="form-select" name="{{ $fieldPrefix }}[activity_type_id]" data-deliverable-activity-type>
                    <option value="">Any activity type</option>
                    @foreach($activityTypes as $type)
                        @php
                            $activityTypeProgramIds = $type->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
                        @endphp
                        <option value="{{ $type->id }}"
                                data-contact-family-id="{{ $type->contact_family_id }}"
                                data-program-ids='@json($activityTypeProgramIds)'
                                data-scope-mode="{{ $type->program_scope_mode?->value ?? 'all' }}"
                                data-global="{{ empty($activityTypeProgramIds) ? 'true' : 'false' }}"
                                data-duration-hours="{{ (float) $type->duration_hours > 0 ? $type->duration_hours : '' }}"
                                data-duration-days="{{ (float) $type->duration_days > 0 ? $type->duration_days : '' }}"
                                @selected((string) ($row['activity_type_id'] ?? '') === (string) $type->id)>
                            {{ $type->name }}
                        </option>
                    @endforeach
                </select>
            </x-form-field>

            <x-form-field label="Program Filter" class="mb-0">
                <select class="form-select" name="{{ $fieldPrefix }}[program_id]" data-deliverable-program>
                    <option value="">Any selected agreement program</option>
                </select>
            </x-form-field>
        </div>

        <div class="{{ $classificationLocked ? '' : 'd-none' }}" data-deliverable-classification-readonly>
            <dl class="mb-0 small">
                <dt class="text-muted fw-normal">Activity Family</dt>
                <dd class="mb-2" data-readonly-contact-family>{{ $contactFamilyLabel }}</dd>
                <dt class="text-muted fw-normal">Activity Type</dt>
                <dd class="mb-2" data-readonly-activity-type>{{ $activityTypeLabel }}</dd>
                <dt class="text-muted fw-normal">Program Filter</dt>
                <dd class="mb-0" data-readonly-program>{{ $programLabel }}</dd>
            </dl>
        </div>
    </x-form-subsection>

    <div class="{{ $semanticLocked ? 'd-none' : '' }}" data-deliverable-requirement-editor>
        <x-form-subsection title="Metric">
            <x-form-field label="Metric" required>
                <div class="form-radio-row">
                    @foreach($metricOptions as $value => $label)
                        <x-form-radio-card
                            name="{{ $fieldPrefix }}[metric_type]"
                            :value="$value"
                            :label="$label"
                            :checked="($row['metric_type'] ?? '') === $value"
                            data-deliverable-metric
                        />
                    @endforeach
                </div>
            </x-form-field>

            <div class="{{ ($row['metric_type'] ?? '') === 'time' ? '' : 'd-none' }}" data-time-basis-wrapper>
                <x-form-field label="Time Measurement">
                    <div class="form-radio-row">
                        @foreach($timeBasisOptions as $value => $option)
                            <x-form-radio-card
                                name="{{ $fieldPrefix }}[time_basis]"
                                :value="$value"
                                :label="$option['label']"
                                :description="$option['description']"
                                :checked="$currentTimeBasis === $value"
                                data-deliverable-time-basis
                            />
                        @endforeach
                    </div>
                    <div class="d-flex align-items-start gap-2 text-muted small mt-2 {{ $showAllottedWarning ? '' : 'd-none' }}" data-deliverable-allotted-warning>
                        <i class="bi bi-exclamation-triangle-fill text-warning flex-shrink-0" aria-hidden="true"></i>
                        <span>The selection above has no allotted time.</span>
                    </div>
                </x-form-field>
            </div>

            <div class="{{ in_array($row['metric_type'] ?? '', ['time', 'completion'], true) ? '' : 'd-none' }}" data-metric-details-wrapper>
                <div class="mb-3">
                    <label class="form-label required-label" data-deliverable-target-label>
                        @if(($row['metric_type'] ?? '') === 'completion')
                            Target Completions
                        @else
                            {{ $targetUnitLabel }}
                        @endif
                    </label>
                    <input type="number"
                           class="form-control"
                           name="{{ $fieldPrefix }}[target_quantity]"
                           value="{{ $row['target_quantity'] ?? '' }}"
                           min="0"
                           step="0.1"
                           data-deliverable-target>
                    <div class="form-text">Leave this at 0 to remove target requirement.</div>
                    <div class="mt-2 {{ $showTargetUnitPicker ? '' : 'd-none' }}" data-target-unit-wrapper>
                        <div class="d-flex gap-3">
                            <label class="form-check mb-0">
                                <input class="form-check-input"
                                       type="radio"
                                       name="{{ $fieldPrefix }}[allotted_time_unit]"
                                       value="days"
                                       data-deliverable-target-unit
                                       data-target-unit="days"
                                       @checked($currentAllottedTimeUnit === 'days')>
                                <span class="form-check-label">Days</span>
                            </label>
                            <label class="form-check mb-0">
                                <input class="form-check-input"
                                       type="radio"
                                       name="{{ $fieldPrefix }}[allotted_time_unit]"
                                       value="hours"
                                       data-deliverable-target-unit
                                       data-target-unit="hours"
                                       @checked($currentAllottedTimeUnit === 'hours')>
                                <span class="form-check-label">Hours</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="{{ $showAdditionalTime ? '' : 'd-none' }}" data-additional-time-wrapper>
                    <div class="mb-0">
                        <div class="form-label">Prep and Follow Up</div>
                        <p class="text-muted small mb-2" data-additional-time-message>
                            @if(!empty($row['contact_family_id']))
                                Should this time count toward progress?
                            @endif
                        </p>
                        <div class="form-check form-switch m-0 ps-0 d-flex align-items-start gap-2">
                            <input class="form-check-input ms-0 mt-1"
                                   type="checkbox"
                                   role="switch"
                                   name="{{ $fieldPrefix }}[include_additional_time]"
                                   value="1"
                                   data-deliverable-additional-time
                                   {{ !empty($row['include_additional_time']) ? 'checked' : '' }}>
                            <label class="form-check-label">Include in deliverable progress</label>
                        </div>
                    </div>
                </div>
            </div>
        </x-form-subsection>

        <x-form-subsection title="Assignment">
            <x-form-field label="Contribution Basis" required>
                <div class="form-radio-row">
                    @foreach($basisOptions as $value => $option)
                        <x-form-radio-card
                            name="{{ $fieldPrefix }}[contribution_basis]"
                            :value="$value"
                            :label="$option['label']"
                            :description="$option['description']"
                            :checked="($row['contribution_basis'] ?? '') === $value"
                            data-deliverable-basis
                        />
                    @endforeach
                </div>
            </x-form-field>

            <div class="{{ ($row['contribution_basis'] ?? '') === 'user' ? '' : 'd-none' }}" data-grouping-wrapper>
                <x-form-field label="Grouping" required class="mb-0">
                    <div class="form-radio-row">
                        @foreach($groupingOptions as $value => $option)
                            <x-form-radio-card
                                name="{{ $fieldPrefix }}[user_grouping_mode]"
                                :value="$value"
                                :label="$option['label']"
                                :description="$option['description']"
                                :checked="($row['user_grouping_mode'] ?? '') === $value"
                                data-deliverable-grouping
                            />
                        @endforeach
                    </div>
                </x-form-field>
            </div>
        </x-form-subsection>
    </div>

    <div class="{{ $semanticLocked ? '' : 'd-none' }}" data-deliverable-requirement-readonly>
        <x-form-subsection title="Metric">
            <div class="alert alert-warning small py-2 {{ $semanticLocked ? '' : 'd-none' }}" data-deliverable-semantic-lock-notice>
                Counting rules are locked. Target, due date, notes, and members can still change.
            </div>
            <dl class="mb-3 small">
                <dt class="text-muted fw-normal">Metric</dt>
                <dd class="mb-2" data-readonly-metric>
                    @if(($row['metric_type'] ?? '') === 'time')
                        {{ $currentTimeBasis === 'allotted' ? 'Allotted time' : 'Time' }}
                    @else
                        {{ $metricOptions[$row['metric_type'] ?? ''] ?? '—' }}
                    @endif
                </dd>
                <dt class="text-muted fw-normal">Contribution Basis</dt>
                <dd class="mb-2" data-readonly-basis>{{ $basisOptions[$row['contribution_basis'] ?? '']['label'] ?? '—' }}</dd>
                <dt class="text-muted fw-normal">Grouping</dt>
                <dd class="mb-2" data-readonly-grouping>{{ $groupingOptions[$row['user_grouping_mode'] ?? '']['label'] ?? '—' }}</dd>
                <dt class="text-muted fw-normal">Include Additional Time</dt>
                <dd class="mb-0" data-readonly-additional-time>{{ !empty($row['include_additional_time']) ? 'Yes' : 'No' }}</dd>
            </dl>
            <div class="mb-0">
                <label class="form-label required-label" data-deliverable-target-label-locked>
                    @if(($row['metric_type'] ?? '') === 'completion')
                        Target Completions
                    @else
                        {{ $targetUnitLabel }}
                    @endif
                </label>
                <input type="number"
                       class="form-control"
                       name="{{ $fieldPrefix }}[target_quantity]"
                       value="{{ $row['target_quantity'] ?? '' }}"
                       min="0"
                       step="0.1"
                       data-deliverable-target-locked>
                <div class="form-text">Leave this at 0 to remove target requirement.</div>
            </div>
        </x-form-subsection>
    </div>

    <div class="{{ ($row['contribution_basis'] ?? '') === 'user' ? '' : 'd-none' }}" data-user-assignment-wrapper>
        <x-form-subsection title="Members">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <p class="text-muted small mb-0">Select from agreement members. Historical contributions use activity snapshots automatically.</p>
                <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0 d-none" data-deliverable-select-all>Select All</button>
            </div>

            <div class="border rounded overflow-auto" style="min-height: 140px; max-height: 240px; background-color: #e9ecef;">
                <div class="small text-muted px-3 py-2 border-bottom bg-body">
                    Agreement members
                </div>
                <div class="m-3" data-deliverable-assignment-ledger>
                    <div class="text-muted small py-3" data-deliverable-assignment-empty>
                        Add teams or users in Teams &amp; Users on the agreement form.
                    </div>
                </div>
            </div>
        </x-form-subsection>
    </div>

    <x-form-subsection title="Notes">
        <x-form-field label="Suggested Due Date">
            <input type="date" class="form-control" name="{{ $fieldPrefix }}[suggested_due_date]" value="{{ $row['suggested_due_date'] ?? '' }}" data-deliverable-due-date>
        </x-form-field>
        <x-form-field label="Notes" class="mb-0">
            <textarea class="form-control" name="{{ $fieldPrefix }}[notes]" rows="2" maxlength="500" data-deliverable-notes>{{ $row['notes'] ?? '' }}</textarea>
        </x-form-field>
    </x-form-subsection>
</div>

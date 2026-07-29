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
            'description' => 'Count activity at the contact level. No user or team assignment is needed.',
        ],
        'user' => [
            'label' => 'By User',
            'description' => 'Attribute activity to specific users or teams. At least one assignee is required.',
        ],
    ];
    $groupingOptions = [
        'joint' => [
            'label' => 'Joint',
            'description' => 'One shared target. Multiple users and teams can contribute together.',
        ],
        'individual' => [
            'label' => 'Individual',
            'description' => 'One target per assigned user. Select one or more users; each tracks progress separately toward the same target.',
        ],
    ];

    $selectedContactFamily = $contactFamilies->firstWhere('id', $row['contact_family_id'] ?? null);
    $timeBasisOptions = [
        'observed' => 'Observed',
        'allotted' => 'Allotted',
    ];

    $selectedActivityType = $activityTypes->firstWhere('id', $row['activity_type_id'] ?? null);
    $currentTimeBasis = ($row['metric_type'] ?? '') === 'time'
        ? ($row['time_basis'] ?? 'observed')
        : 'observed';
    $showAdditionalTime = ($row['metric_type'] ?? '') === 'time'
        && $currentTimeBasis === 'observed'
        && !empty($row['contact_family_id'])
        && $selectedContactFamily?->track_additional_time;
@endphp

<div class="deliverable-fields" data-deliverable-fields>
    <div class="alert alert-warning small {{ $classificationLocked ? '' : 'd-none' }}" data-deliverable-classification-lock-notice>
        Classification is locked because matching activity history exists. Create a new deliverable to change contact family, activity type, or program filter.
    </div>

    <div class="alert alert-warning small {{ $semanticLocked ? '' : 'd-none' }}" data-deliverable-semantic-lock-notice>
        Counting rules are locked because matching activity history exists. You can still update target, due date, notes, and assignments.
    </div>

    {{-- Classification --}}
    <div class="mb-4">
        <h6 class="fw-semibold mb-1">Classification</h6>
        <p class="text-muted small mb-3">Define which logged activity counts toward this deliverable.</p>

        <div class="{{ $classificationLocked ? 'd-none' : '' }}" data-deliverable-classification-editor>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Contact Family <span class="text-danger">*</span></label>
                    <select class="form-select" name="{{ $fieldPrefix }}[contact_family_id]" data-deliverable-contact-family>
                        <option value="">Select contact family...</option>
                        @foreach($contactFamilies as $family)
                            @php
                                $familyProgramIds = $family->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
                            @endphp
                            <option value="{{ $family->id }}"
                                    data-program-ids='@json($familyProgramIds)'
                                    data-global="{{ empty($familyProgramIds) ? 'true' : 'false' }}"
                                    data-track-additional-time="{{ $family->track_additional_time ? '1' : '0' }}"
                                    @selected((string) ($row['contact_family_id'] ?? '') === (string) $family->id)>
                                {{ $family->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Activity Type</label>
                    <select class="form-select" name="{{ $fieldPrefix }}[activity_type_id]" data-deliverable-activity-type>
                        <option value="">Any activity type</option>
                        @foreach($activityTypes as $type)
                            @php
                                $activityTypeProgramIds = $type->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
                            @endphp
                            <option value="{{ $type->id }}"
                                    data-contact-family-id="{{ $type->contact_family_id }}"
                                    data-program-ids='@json($activityTypeProgramIds)'
                                    data-global="{{ empty($activityTypeProgramIds) ? 'true' : 'false' }}"
                                    data-duration-hours="{{ (float) $type->duration_hours > 0 ? $type->duration_hours : '' }}"
                                    data-duration-days="{{ (float) $type->duration_days > 0 ? $type->duration_days : '' }}"
                                    @selected((string) ($row['activity_type_id'] ?? '') === (string) $type->id)>
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Program Filter</label>
                    <select class="form-select" name="{{ $fieldPrefix }}[program_id]" data-deliverable-program>
                        <option value="">Any selected agreement program</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="{{ $classificationLocked ? '' : 'd-none' }}" data-deliverable-classification-readonly>
            <dl class="row mb-0 small">
                <dt class="col-sm-4 text-muted fw-normal">Contact Family</dt>
                <dd class="col-sm-8 mb-2" data-readonly-contact-family>{{ $contactFamilyLabel }}</dd>
                <dt class="col-sm-4 text-muted fw-normal">Activity Type</dt>
                <dd class="col-sm-8 mb-2" data-readonly-activity-type>{{ $activityTypeLabel }}</dd>
                <dt class="col-sm-4 text-muted fw-normal">Program Filter</dt>
                <dd class="col-sm-8 mb-0" data-readonly-program>{{ $programLabel }}</dd>
            </dl>
        </div>
    </div>

    {{-- Details --}}
    <div class="mb-4">
        <h6 class="fw-semibold mb-1">Details</h6>
        <p class="text-muted small mb-3">Optional scheduling and context for staff reviewing this deliverable.</p>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Suggested Due Date</label>
                <input type="date" class="form-control" name="{{ $fieldPrefix }}[suggested_due_date]" value="{{ $row['suggested_due_date'] ?? '' }}" data-deliverable-due-date>
            </div>
            <div class="col-md-8">
                <label class="form-label">Notes</label>
                <textarea class="form-control" name="{{ $fieldPrefix }}[notes]" rows="2" maxlength="500" data-deliverable-notes>{{ $row['notes'] ?? '' }}</textarea>
            </div>
        </div>
    </div>

    {{-- Requirement --}}
    <div class="mb-4">
        <h6 class="fw-semibold mb-1">Requirement</h6>
        <p class="text-muted small mb-3">How progress is measured and attributed.</p>

        <div class="{{ $semanticLocked ? 'd-none' : '' }}" data-deliverable-requirement-editor>
            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold d-block mb-1">Metric <span class="text-danger">*</span></label>
                    <p class="text-muted small mb-2">Choose whether this deliverable tracks logged time or activity completions.</p>
                    <div class="d-grid gap-2">
                        @foreach($metricOptions as $value => $label)
                            <label class="form-check border rounded px-3 py-2 mb-0 {{ ($row['metric_type'] ?? '') === $value ? 'border-primary bg-light' : '' }}">
                                <input class="form-check-input me-2"
                                       type="radio"
                                       name="{{ $fieldPrefix }}[metric_type]"
                                       value="{{ $value }}"
                                       data-deliverable-metric
                                       {{ ($row['metric_type'] ?? '') === $value ? 'checked' : '' }}>
                                <span class="form-check-label fw-semibold">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-md-6" data-time-basis-wrapper>
                    <label class="form-label fw-semibold d-block mb-1">Time Measurement</label>
                    <p class="text-muted small mb-2">Choose observed logged hours or allotted duration from the activity type.</p>
                    <div class="d-grid gap-2">
                        @foreach($timeBasisOptions as $value => $label)
                            <label class="form-check border rounded px-3 py-2 mb-0 {{ $currentTimeBasis === $value ? 'border-primary bg-light' : '' }}">
                                <input class="form-check-input me-2"
                                       type="radio"
                                       name="{{ $fieldPrefix }}[time_basis]"
                                       value="{{ $value }}"
                                       data-deliverable-time-basis
                                       {{ $currentTimeBasis === $value ? 'checked' : '' }}>
                                <span class="form-check-label fw-semibold">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4 {{ in_array($row['metric_type'] ?? '', ['time', 'completion'], true) ? '' : 'd-none' }}" data-metric-details-wrapper>
                <div class="col-md-4">
                    <label class="form-label fw-semibold d-block mb-1" data-deliverable-target-label>
                        @if(($row['metric_type'] ?? '') === 'completion')
                            Target Completions <span class="text-danger">*</span>
                        @elseif($currentTimeBasis === 'allotted' && (float) ($selectedActivityType?->duration_days ?? 0) > 0)
                            Target Days <span class="text-danger">*</span>
                        @else
                            Target Hours <span class="text-danger">*</span>
                        @endif
                    </label>
                    <input type="number"
                           class="form-control"
                           name="{{ $fieldPrefix }}[target_quantity]"
                           value="{{ $row['target_quantity'] ?? '' }}"
                           min="0"
                           step="0.1"
                           data-deliverable-target>
                    <div class="form-text {{ $currentTimeBasis === 'allotted' && $selectedActivityType && ((float) $selectedActivityType->duration_days > 0 || (float) $selectedActivityType->duration_hours > 0) ? '' : 'd-none' }}" data-deliverable-duration-reminder>
                        @if((float) ($selectedActivityType?->duration_days ?? 0) > 0)
                            Each completion: {{ rtrim(rtrim(number_format((float) $selectedActivityType->duration_days, 1, '.', ''), '0'), '.') }} {{ (float) $selectedActivityType->duration_days == 1 ? 'day' : 'days' }}
                        @elseif((float) ($selectedActivityType?->duration_hours ?? 0) > 0)
                            Each completion: {{ rtrim(rtrim(number_format((float) $selectedActivityType->duration_hours, 1, '.', ''), '0'), '.') }} {{ (float) $selectedActivityType->duration_hours == 1 ? 'hour' : 'hours' }}
                        @endif
                    </div>
                </div>

                <div class="col-md-8 {{ $showAdditionalTime ? '' : 'd-none' }}" data-additional-time-wrapper>
                    <label class="form-label fw-semibold d-block mb-1">Prep and Follow Up Time</label>
                    <p class="text-muted small mb-2" data-additional-time-message>
                        @if(!empty($row['contact_family_id']))
                            The {{ $contactFamilyLabel }} contact family requires prep and follow up time to be reported in activity logging. Should this time contribute to deliverable progress?
                        @endif
                    </p>
                    <div class="form-check form-switch">
                        <input class="form-check-input"
                               type="checkbox"
                               role="switch"
                               name="{{ $fieldPrefix }}[include_additional_time]"
                               value="1"
                               data-deliverable-additional-time
                               {{ !empty($row['include_additional_time']) ? 'checked' : '' }}>
                        <label class="form-check-label">Include prep and follow up time in deliverable progress</label>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-lg-6">
                    <label class="form-label fw-semibold d-block mb-1">Contribution Basis <span class="text-danger">*</span></label>
                    <p class="text-muted small mb-2">Choose whether activity counts at the contact level or is attributed to specific users.</p>
                    <div class="d-grid gap-2">
                        @foreach($basisOptions as $value => $option)
                            <label class="form-check border rounded px-3 py-2 mb-0 {{ ($row['contribution_basis'] ?? '') === $value ? 'border-primary bg-light' : '' }}">
                                <input class="form-check-input me-2"
                                       type="radio"
                                       name="{{ $fieldPrefix }}[contribution_basis]"
                                       value="{{ $value }}"
                                       data-deliverable-basis
                                       {{ ($row['contribution_basis'] ?? '') === $value ? 'checked' : '' }}>
                                <span class="form-check-label fw-semibold">{{ $option['label'] }}</span>
                                <span class="text-muted small d-block">{{ $option['description'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="col-lg-6 {{ ($row['contribution_basis'] ?? '') === 'user' ? '' : 'd-none' }}" data-grouping-wrapper>
                    <label class="form-label fw-semibold d-block mb-1">Grouping <span class="text-danger">*</span></label>
                    <p class="text-muted small mb-2">Choose how user-based targets are applied across assignees.</p>
                    <div class="d-grid gap-2">
                        @foreach($groupingOptions as $value => $option)
                            <label class="form-check border rounded px-3 py-2 mb-0 {{ ($row['user_grouping_mode'] ?? '') === $value ? 'border-primary bg-light' : '' }}">
                                <input class="form-check-input me-2"
                                       type="radio"
                                       name="{{ $fieldPrefix }}[user_grouping_mode]"
                                       value="{{ $value }}"
                                       data-deliverable-grouping
                                       {{ ($row['user_grouping_mode'] ?? '') === $value ? 'checked' : '' }}>
                                <span class="form-check-label fw-semibold">{{ $option['label'] }}</span>
                                <span class="text-muted small d-block">{{ $option['description'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="{{ $semanticLocked ? '' : 'd-none' }}" data-deliverable-requirement-readonly>
            <dl class="row mb-3 small">
                <dt class="col-sm-4 text-muted fw-normal">Metric</dt>
                <dd class="col-sm-8 mb-2" data-readonly-metric>
                    @if(($row['metric_type'] ?? '') === 'time')
                        {{ $currentTimeBasis === 'allotted' ? 'Allotted time' : 'Time' }}
                    @else
                        {{ $metricOptions[$row['metric_type'] ?? ''] ?? '—' }}
                    @endif
                </dd>
                <dt class="col-sm-4 text-muted fw-normal">Contribution Basis</dt>
                <dd class="col-sm-8 mb-2" data-readonly-basis>{{ $basisOptions[$row['contribution_basis'] ?? '']['label'] ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted fw-normal">Grouping</dt>
                <dd class="col-sm-8 mb-2" data-readonly-grouping>{{ $groupingOptions[$row['user_grouping_mode'] ?? '']['label'] ?? '—' }}</dd>
                <dt class="col-sm-4 text-muted fw-normal">Include Additional Time</dt>
                <dd class="col-sm-8 mb-0" data-readonly-additional-time>{{ !empty($row['include_additional_time']) ? 'Yes' : 'No' }}</dd>
            </dl>
            <div class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label fw-semibold d-block mb-1" data-deliverable-target-label-locked>
                        @if(($row['metric_type'] ?? '') === 'completion')
                            Target Completions
                        @elseif($currentTimeBasis === 'allotted' && (float) ($selectedActivityType?->duration_days ?? 0) > 0)
                            Target Days
                        @else
                            Target Hours
                        @endif
                        <span class="text-danger">*</span>
                    </label>
                    <input type="number"
                           class="form-control"
                           name="{{ $fieldPrefix }}[target_quantity]"
                           value="{{ $row['target_quantity'] ?? '' }}"
                           min="0"
                           step="0.1"
                           data-deliverable-target-locked>
                </div>
            </div>
        </div>
    </div>

    {{-- Assignment --}}
    <div class="{{ ($row['contribution_basis'] ?? '') === 'user' ? '' : 'd-none' }}" data-user-assignment-wrapper>
        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h6 class="fw-semibold mb-1">Assignment</h6>
                <p class="text-muted small mb-0">Select from agreement members below. Historical contributions use activity snapshots automatically.</p>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0 d-none" data-deliverable-select-all>Select All</button>
        </div>

        <div class="border rounded overflow-auto" style="min-height: 180px; max-height: 320px; background-color: #e9ecef;">
            <div class="small text-muted px-3 py-2 border-bottom bg-body">
                Agreement members
            </div>
            <div class="m-3" data-deliverable-assignment-ledger>
                <div class="text-muted small py-3" data-deliverable-assignment-empty>
                    Add teams or users to the agreement above before assigning this deliverable.
                </div>
            </div>
        </div>
    </div>
</div>

@php
    $renderContributorLabel = function (array $row, bool $includeTeam = true): string {
        $label = $row['user']->name;
        if ($includeTeam && !empty($row['team_name'])) {
            $label .= ' from ' . $row['team_name'];
        }

        return $label;
    };

    $isDefaultLabel = function (string $label): bool {
        return str_starts_with($label, 'Any ');
    };

    $assigneeCount = function (array $progress): int {
        if ($progress['is_individual']) {
            return $progress['individual_progress']->count();
        }

        if ($progress['is_joint']) {
            return $progress['live_assignment_groups']->flatMap(fn ($group) => $group['users'])->count();
        }

        if ($progress['is_contact']) {
            return $progress['tagged_assignment_groups']->flatMap(fn ($group) => $group['users'])->count();
        }

        return 0;
    };

    $allocationWarningTooltip = function (array $notices, string $unitLabel): string {
        $parts = [];

        if (!empty($notices['unassigned_remainder'])) {
            $parts[] = number_format($notices['unassigned_remainder'], 1) . ' ' . strtolower($unitLabel) . ' of the target is not assigned to anyone';
        }

        if (!empty($notices['over_assigned_by'])) {
            $parts[] = 'Assigned targets exceed the deliverable total by ' . number_format($notices['over_assigned_by'], 1) . ' ' . strtolower($unitLabel);
        }

        return implode('; ', $parts);
    };
@endphp

@if($deliverableGroups->isEmpty())
    <p class="text-muted small mb-0">No deliverables defined for this agreement.</p>
@else
    @if(!empty($deliverableActivityBuckets['buckets']))
        <div class="mb-3">
            <div class="text-muted small fw-semibold mb-2">Activity over agreement period</div>
            <div class="deliverable-activity-histogram" style="height: 140px;">
                <canvas id="deliverable-activity-histogram"
                        data-activity-buckets='@json($deliverableActivityBuckets)'
                        data-selected-from="{{ $deliverableFrom?->format('Y-m-d') }}"
                        data-selected-to="{{ $deliverableTo?->format('Y-m-d') }}"></canvas>
            </div>
        </div>
    @endif

    <form method="GET" action="{{ route('agreements.show', $agreement) }}" id="deliverable-date-filter" class="mb-3">
        <div class="d-flex flex-wrap align-items-end gap-3">
            <div>
                <label for="deliverable-from" class="form-label small mb-1">From</label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="deliverable-from"
                       name="from"
                       value="{{ $deliverableFrom?->format('Y-m-d') }}">
            </div>
            <div>
                <label for="deliverable-to" class="form-label small mb-1">To</label>
                <input type="date"
                       class="form-control form-control-sm"
                       id="deliverable-to"
                       name="to"
                       value="{{ $deliverableTo?->format('Y-m-d') }}">
                @if(!empty($usingExtendedEnd))
                    <div class="form-text">Extended end</div>
                @endif
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-outline-primary">Apply</button>
                <a href="{{ route('agreements.show', $agreement) }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </div>
        <p class="form-text mb-0 mt-2">
            Status is computed from the agreement start and end dates{{ $agreement->extension_end_date ? ' (extended end when set)' : '' }}.
        </p>
    </form>

    @if(!empty($missingAgreementDates))
        <div class="alert alert-warning alert-dismissible fade show py-2 small d-flex align-items-center" role="alert">
            <span class="flex-grow-1 pe-2">No start and end dates set on the agreement. Deliverable statuses not computed.</span>
            <button type="button" class="btn-close position-static p-0" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(!empty($startDateAfterToday))
        <div class="alert alert-warning alert-dismissible fade show py-2 small d-flex align-items-center" role="alert">
            <span class="flex-grow-1 pe-2">The current start date is after today. Status cannot be computed.</span>
            <button type="button" class="btn-close position-static p-0" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @foreach($deliverableGroups as $familyGroup)
        <div class="mb-4 {{ !$loop->last ? 'pb-4 border-bottom' : '' }}">
            <h5 class="fw-semibold mb-3">{{ $familyGroup['contact_family_label'] }}</h5>

            @foreach($familyGroup['activity_groups'] as $activityGroup)
                @php
                    $showActivityHeading = ! $isDefaultLabel($activityGroup['activity_type_label'])
                        || $familyGroup['activity_groups']->count() > 1;
                @endphp

                <div class="mb-3 {{ $showActivityHeading ? 'ps-2 border-start border-3 border-light' : '' }}">
                    @if($showActivityHeading)
                        <h6 class="fw-semibold text-muted mb-2">{{ $activityGroup['activity_type_label'] }}</h6>
                    @endif

                    @foreach($activityGroup['program_groups'] as $programGroup)
                        @php
                            $showProgramHeading = ! $isDefaultLabel($programGroup['program_label'])
                                || $activityGroup['program_groups']->count() > 1;
                        @endphp

                        <div class="mb-3 {{ $showProgramHeading ? 'ps-3' : '' }}">
                            @if($showProgramHeading)
                                <div class="small text-muted mb-2">
                                    <i class="bi bi-funnel me-1"></i>{{ $programGroup['program_label'] }}
                                </div>
                            @endif

                            @foreach($programGroup['deliverables'] as $progress)
                                @php
                                    $deliverable = $progress['deliverable'];
                                    $target = $progress['target'];
                                    $hasTarget = $progress['has_target'];
                                    $completedValue = $progress['completed_value'];
                                    $percent = $progress['percent'];
                                    $unitLabel = $progress['unit_label'];
                                    $collapseId = 'deliverable-detail-' . $deliverable->id;
                                    $displayCompleted = $progress['is_individual'] && $hasTarget
                                        ? ($progress['counted_completed_value'] ?? $completedValue)
                                        : $completedValue;
                                    $memberCount = $assigneeCount($progress);

                                    $allocationNotices = $progress['allocation_notices'] ?? [];
                                    $assignmentWarnings = array_filter([
                                        'unassigned_remainder' => $allocationNotices['unassigned_remainder'] ?? null,
                                        'over_assigned_by' => $allocationNotices['over_assigned_by'] ?? null,
                                    ]);

                                    $metaItems = [];
                                    if (!empty($progress['rollup_on_track'])) {
                                        $metaItems[] = [
                                            'text' => $progress['rollup_on_track']['on_track'] . ' of ' . $progress['rollup_on_track']['total'] . ' on track',
                                            'class' => 'text-muted',
                                        ];
                                    }
                                    if (!empty($allocationNotices['unassigned_remainder'])) {
                                        $metaItems[] = [
                                            'text' => number_format($allocationNotices['unassigned_remainder'], 1) . ' ' . strtolower($unitLabel) . ' unassigned',
                                            'class' => 'text-secondary',
                                        ];
                                    }
                                    if (!empty($allocationNotices['over_assigned_by'])) {
                                        $metaItems[] = [
                                            'text' => 'Over-assigned by ' . number_format($allocationNotices['over_assigned_by'], 1),
                                            'class' => 'text-secondary',
                                        ];
                                    }
                                    if (!empty($allocationNotices['excess_logged'])) {
                                        $metaItems[] = [
                                            'text' => 'Excess logged ' . number_format($allocationNotices['excess_logged'], 1),
                                            'class' => 'text-warning-emphasis',
                                        ];
                                    }
                                @endphp

                                <div class="border rounded mb-2 bg-body">
                                    <button type="button"
                                            class="w-100 btn btn-link text-decoration-none text-body text-start p-3"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#{{ $collapseId }}"
                                            aria-expanded="false"
                                            aria-controls="{{ $collapseId }}">
                                        <div class="d-flex justify-content-between align-items-start gap-3">
                                            <div class="flex-grow-1">
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="fw-semibold">{{ $progress['metric_summary'] }}</span>
                                                    @if(!empty($assignmentWarnings))
                                                        <i class="bi bi-exclamation-triangle-fill text-warning flex-shrink-0"
                                                           data-bs-toggle="tooltip"
                                                           data-bs-placement="top"
                                                           title="{{ $allocationWarningTooltip($assignmentWarnings, $unitLabel) }}"
                                                           aria-hidden="true"></i>
                                                    @endif
                                                </div>
                                                @if($deliverable->suggested_due_date)
                                                    <div class="text-muted small">Suggested due {{ $deliverable->suggested_due_date->format('M d, Y') }}</div>
                                                @endif
                                                @if($memberCount > 0)
                                                    <div class="text-muted small">{{ $memberCount }} {{ $memberCount === 1 ? 'assignee' : 'assignees' }}</div>
                                                @endif
                                            </div>
                                            <div class="text-end small text-nowrap">
                                                <div>
                                                    <strong>{{ number_format($displayCompleted, 1) }}</strong>
                                                    @if($hasTarget)
                                                        <span class="text-muted">/ {{ number_format($target, 1) }}</span>
                                                    @endif
                                                    <span class="text-muted">{{ $unitLabel }}</span>
                                                </div>
                                                <x-deliverable-status :status="$progress['status'] ?? null" class="mt-1" />
                                                <div class="text-muted mt-1"><i class="bi bi-chevron-down" aria-hidden="true"></i></div>
                                            </div>
                                        </div>

                                        @if($progress['is_individual'] && !empty($progress['sectioned_bar']) && $hasTarget)
                                            <div class="d-flex mt-2 rounded overflow-hidden" style="height:8px;">
                                                @foreach($progress['sectioned_bar']['sections'] as $section)
                                                    @if(($section['type'] ?? '') === 'unassigned')
                                                        <div class="bg-warning border-end border-white" style="width:{{ $section['width_percent'] }}%"></div>
                                                    @else
                                                        <div class="bg-body-secondary border-end border-white" style="width:{{ $section['width_percent'] }}%">
                                                            <div class="h-100 {{ ($section['fill_percent'] ?? 0) >= 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ min(100, (float) ($section['fill_percent'] ?? 0)) }}%"></div>
                                                        </div>
                                                    @endif
                                                @endforeach
                                            </div>
                                        @elseif(!$progress['is_individual'] && $hasTarget)
                                            <div class="progress mt-2" style="height:8px;">
                                                <div class="progress-bar {{ $percent >= 100 ? 'bg-success' : 'bg-primary' }}" style="width:{{ $percent }}%"></div>
                                            </div>
                                        @endif
                                    </button>

                                    @if(!empty($metaItems))
                                        <div class="px-3 pb-3 small d-flex flex-wrap align-items-center gap-2">
                                            @foreach($metaItems as $metaItem)
                                                @if(!$loop->first)
                                                    <span class="text-body-tertiary" aria-hidden="true">&middot;</span>
                                                @endif
                                                <span class="{{ $metaItem['class'] }}">{{ $metaItem['text'] }}</span>
                                            @endforeach
                                        </div>
                                    @endif

                                    <div class="collapse" id="{{ $collapseId }}" data-burn-up='@json($progress['burn_up'] ?? [])'>
                                        <div class="px-3 pb-3 border-top pt-3">
                                            @include('agreements.partials.show-deliverable-detail', [
                                                'progress' => $progress,
                                                'renderContributorLabel' => $renderContributorLabel,
                                            ])
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endforeach
@endif

@push('scripts')
    @vite(['resources/js/deliverable-charts.js'])
@endpush

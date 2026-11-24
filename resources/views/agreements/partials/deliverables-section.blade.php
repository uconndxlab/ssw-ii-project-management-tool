@php
    $subActivityDurationMap = $activityTypes->flatMap(fn ($type) => $type->subActivities)->unique('id')->mapWithKeys(fn ($sub) => [(string) $sub->id => [
            'typeId' => (string) $sub->activity_type_id,
            'hours' => (float) $sub->duration_hours > 0 ? (float) $sub->duration_hours : null,
            'days' => (float) $sub->duration_days > 0 ? (float) $sub->duration_days : null,
            'programIds' => $sub->programs->pluck('id')->map(fn ($id) => (string) $id)->all(),
            'scopeMode' => $sub->program_scope_mode?->value ?? 'all',
        ]]);
    $rawDeliverableRows = old('deliverables');
    $deliverableRows = [];
    $hasDeliverableRows = false;

    $programLookup = collect($projects ?? [])
        ->flatMap(fn ($project) => $project->programs ?? collect())
        ->merge($agreement?->programs ?? collect())
        ->unique('id')
        ->keyBy('id');

    $buildRulesSummary = function (array $row) use ($activityTypes, $subActivities): string {
        $parts = [];
        if (!empty($row['metric_type'])) {
            if ($row['metric_type'] === 'time') {
                $parts[] = ($row['time_basis'] ?? 'observed') === 'allotted' ? 'Allotted time' : 'Time';
            } else {
                $parts[] = ucfirst($row['metric_type']);
            }
        }
        if (!empty($row['contribution_basis'])) {
            $parts[] = ucfirst($row['contribution_basis']);
        }
        if (($row['contribution_basis'] ?? '') === 'user' && !empty($row['user_grouping_mode'])) {
            $parts[] = ucfirst($row['user_grouping_mode']);
        }
        if ($row['target_quantity'] !== '' && $row['target_quantity'] !== null) {
            $suffix = '';
            if (($row['metric_type'] ?? '') === 'time') {
                $isAllotted = ($row['time_basis'] ?? 'observed') === 'allotted';
                $unit = $row['allotted_time_unit'] ?? null;
                if (!$unit && $isAllotted) {
                    $activityType = collect($activityTypes ?? [])->firstWhere('id', $row['activity_type_id'] ?? null);
                    $duration = \App\Support\ActivityTypeDuration::effective($activityType, $subActivities->firstWhere('id', $row['sub_activity_id'] ?? null));
                    if ($duration->unit() === 'days') {
                        $unit = 'days';
                    } elseif ($duration->unit() === 'hours') {
                        $unit = 'hours';
                    }
                }
                $suffix = ($isAllotted && $unit === 'days') ? ' days' : ' hrs';
            }
            $targetQuantity = (float) $row['target_quantity'];
            $parts[] = $targetQuantity > 0
                ? number_format($targetQuantity, 1) . $suffix
                : 'No target';
        }
        if (!empty($row['include_additional_time'])) {
            $parts[] = 'Incl. prep/follow up';
        }

        return implode(' · ', $parts);
    };

    $buildAssignmentGroups = function (array $row) use ($teams, $users): array {
        $groups = [];
        $groupedUserIds = collect();
        $isJoint = ($row['user_grouping_mode'] ?? '') === 'joint';
        $isContact = ($row['contribution_basis'] ?? '') === 'contact';
        $usesTeamExpansion = $isJoint || $isContact;
        $assignedUserIds = collect($row['user_ids'] ?? [])->map(fn ($id) => (int) $id);

        foreach ($row['team_ids'] ?? [] as $teamId) {
            $team = $teams->firstWhere('id', (int) $teamId);
            if (!$team) {
                continue;
            }

            if ($usesTeamExpansion) {
                $teamUserNames = $team->users->sortBy('name')->pluck('name')->values()->all();
                $groupedUserIds = $groupedUserIds->merge($team->users->pluck('id')->map(fn ($id) => (int) $id));
            } else {
                $teamUserNames = $assignedUserIds
                    ->filter(fn (int $userId) => $team->users->contains('id', $userId))
                    ->map(fn (int $userId) => $users->firstWhere('id', $userId)?->name)
                    ->filter()
                    ->values()
                    ->all();
                $groupedUserIds = $groupedUserIds->merge(
                    $assignedUserIds->filter(fn (int $userId) => $team->users->contains('id', $userId))
                );
            }

            $groups[] = [
                'team_name' => $team->name,
                'users' => $teamUserNames,
            ];
        }

        $standaloneUsers = $assignedUserIds
            ->diff($groupedUserIds)
            ->map(fn (int $userId) => $users->firstWhere('id', $userId)?->name)
            ->filter()
            ->values()
            ->all();

        if (!empty($standaloneUsers)) {
            $groups[] = [
                'team_name' => null,
                'users' => $standaloneUsers,
            ];
        }

        return $groups;
    };

    $enrichRow = function (array $row, string $rowKey) use ($contactFamilies, $activityTypes, $subActivities, $programLookup, $buildRulesSummary, $buildAssignmentGroups): array {
        return array_merge($row, [
            'row_key' => $rowKey,
            'contact_family_label' => $contactFamilies->firstWhere('id', $row['contact_family_id'] ?? null)?->name,
            'activity_type_label' => ($activityTypes->firstWhere('id', $row['activity_type_id'] ?? null)?->name ?? '').(($sub = $subActivities->firstWhere('id', $row['sub_activity_id'] ?? null)) ? ' › '.$sub->name : ''),
            'program_label' => $programLookup->get((int) ($row['program_id'] ?? 0))?->name,
            'rules_summary' => $buildRulesSummary($row),
            'assignment_groups' => $buildAssignmentGroups($row),
        ]);
    };

    $existingLockMap = [];
    if ($agreement?->deliverables && $agreement->relationLoaded('agreementActivityHistories')) {
        foreach ($agreement->deliverables as $deliverable) {
            $locked = $agreement->agreementActivityHistories->contains(function ($history) use ($deliverable) {
                if ((int) $history->contact_family_id !== (int) $deliverable->contact_family_id) {
                    return false;
                }
                if ($deliverable->activity_type_id && (int) $history->activity_type_id !== (int) $deliverable->activity_type_id) {
                    return false;
                }
                if ($deliverable->sub_activity_id && (int) $history->sub_activity_id !== (int) $deliverable->sub_activity_id) {
                    return false;
                }

                if ($deliverable->program_id) {
                    return collect($history->program_ids_snapshot ?? [])
                        ->map(fn ($id) => (int) $id)
                        ->contains((int) $deliverable->program_id);
                }

                return true;
            });
            $existingLockMap[(int) $deliverable->id] = $locked;
        }
    }

    $normalizeStoredRow = function (array $row, string $rowKey) use ($existingLockMap): array {
        return [
            'row_key' => $rowKey,
            'id' => $row['id'] ?? '',
            '_delete' => !empty($row['_delete']) ? '1' : '0',
            'contact_family_id' => $row['contact_family_id'] ?? '',
            'activity_type_id' => $row['activity_type_id'] ?? '',
            'sub_activity_id' => $row['sub_activity_id'] ?? '',
            'program_id' => $row['program_id'] ?? '',
            'metric_type' => $row['metric_type'] ?? '',
            'time_basis' => $row['time_basis'] ?? 'observed',
            'allotted_time_unit' => $row['allotted_time_unit'] ?? '',
            'contribution_basis' => $row['contribution_basis'] ?? '',
            'user_grouping_mode' => $row['user_grouping_mode'] ?? '',
            'include_additional_time' => !empty($row['include_additional_time']),
            'target_quantity' => $row['target_quantity'] ?? '',
            'suggested_due_date' => $row['suggested_due_date'] ?? '',
            'sort_order' => $row['sort_order'] ?? 0,
            'notes' => $row['notes'] ?? '',
            'user_ids' => collect($row['user_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all(),
            'team_ids' => collect($row['team_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all(),
            'user_assigned_at' => collect($row['user_assigned_at'] ?? [])
                ->mapWithKeys(fn ($value, $id) => [(string) $id => $value ?: ''])
                ->all(),
            'team_assigned_at' => collect($row['team_assigned_at'] ?? [])
                ->mapWithKeys(fn ($value, $id) => [(string) $id => $value ?: ''])
                ->all(),
            'user_targets' => collect($row['user_targets'] ?? [])
                ->mapWithKeys(fn ($value, $id) => [(string) $id => \App\Support\DeliverableAssignmentTargets::formatQuantity($value)])
                ->all(),
            'team_targets' => collect($row['team_targets'] ?? [])
                ->mapWithKeys(fn ($value, $id) => [(string) $id => \App\Support\DeliverableAssignmentTargets::formatQuantity($value)])
                ->all(),
            'classification_locked' => !empty($row['classification_locked']) || ($existingLockMap[(int) ($row['id'] ?? 0)] ?? false),
            'semantic_locked' => !empty($row['semantic_locked']) || ($existingLockMap[(int) ($row['id'] ?? 0)] ?? false),
        ];
    };

    if (is_array($rawDeliverableRows)) {
        foreach ($rawDeliverableRows as $key => $row) {
            if (!is_array($row)) {
                continue;
            }
            $rowKey = is_string($key) ? $key : 'row-' . $key;
            $deliverableRows[] = $enrichRow($normalizeStoredRow($row, $rowKey), $rowKey);
        }
        $hasDeliverableRows = !empty($deliverableRows);
    } elseif ($agreement?->deliverables) {
        foreach ($agreement->deliverables as $deliverable) {
            if ($deliverable->retired_at) {
                continue;
            }
            $row = $normalizeStoredRow([
                'id' => $deliverable->id,
                'contact_family_id' => $deliverable->contact_family_id,
                'activity_type_id' => $deliverable->activity_type_id,
                'sub_activity_id' => $deliverable->sub_activity_id,
                'program_id' => $deliverable->program_id,
                'metric_type' => $deliverable->metric_type,
                'time_basis' => $deliverable->time_basis ?? 'observed',
                'allotted_time_unit' => $deliverable->allotted_time_unit ?? '',
                'contribution_basis' => $deliverable->contribution_basis,
                'user_grouping_mode' => $deliverable->user_grouping_mode,
                'include_additional_time' => (bool) $deliverable->include_additional_time,
                'target_quantity' => $deliverable->target_quantity,
                'suggested_due_date' => $deliverable->suggested_due_date?->format('Y-m-d') ?? '',
                'sort_order' => $deliverable->sort_order ?? 0,
                'notes' => $deliverable->notes ?? '',
                'user_ids' => $deliverable->users->filter(fn ($user) => !$user->pivot->unassigned_at)->pluck('id')->all(),
                'team_ids' => $deliverable->teams->filter(fn ($team) => !$team->pivot->unassigned_at)->pluck('id')->all(),
                'user_assigned_at' => $deliverable->users
                    ->filter(fn ($user) => !$user->pivot->unassigned_at)
                    ->mapWithKeys(fn ($user) => [(string) $user->id => $user->pivot->assigned_at ?? ''])
                    ->all(),
                'team_assigned_at' => $deliverable->teams
                    ->filter(fn ($team) => !$team->pivot->unassigned_at)
                    ->mapWithKeys(fn ($team) => [(string) $team->id => $team->pivot->assigned_at ?? ''])
                    ->all(),
                'user_targets' => $deliverable->users
                    ->filter(fn ($user) => !$user->pivot->unassigned_at)
                    ->mapWithKeys(fn ($user) => [(string) $user->id => \App\Support\DeliverableAssignmentTargets::formatQuantity($user->pivot->target_quantity)])
                    ->all(),
                'team_targets' => $deliverable->teams
                    ->filter(fn ($team) => !$team->pivot->unassigned_at)
                    ->mapWithKeys(fn ($team) => [(string) $team->id => \App\Support\DeliverableAssignmentTargets::formatQuantity($team->pivot->target_quantity)])
                    ->all(),
            ], 'existing-' . $deliverable->id);
            $deliverableRows[] = $enrichRow($row, $row['row_key']);
        }
        $hasDeliverableRows = !empty($deliverableRows);
    }

    $editorDefaults = [
        'id' => '',
        '_delete' => '0',
        'contact_family_id' => '',
        'activity_type_id' => '',
        'sub_activity_id' => '',
        'program_id' => '',
        'metric_type' => '',
        'time_basis' => 'observed',
        'allotted_time_unit' => 'hours',
        'contribution_basis' => '',
        'user_grouping_mode' => '',
        'include_additional_time' => false,
        'target_quantity' => '',
        'suggested_due_date' => '',
        'sort_order' => 0,
        'notes' => '',
        'user_ids' => [],
        'team_ids' => [],
        'user_assigned_at' => [],
        'team_assigned_at' => [],
        'user_targets' => [],
        'team_targets' => [],
        'classification_locked' => false,
        'semantic_locked' => false,
    ];

    $deliverableErrorMessages = collect($errors->messages())
        ->filter(fn ($messages, $key) => $key === 'deliverables' || str_starts_with((string) $key, 'deliverables.'))
        ->flatten()
        ->unique()
        ->values();
@endphp

<x-section-card title="Deliverables" class="overflow-hidden">
        @if($deliverableErrorMessages->isNotEmpty())
            <div class="alert alert-danger small">
                <ul class="mb-0">
                    @foreach($deliverableErrorMessages as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="table-responsive mb-3 mw-100">
            <table class="table table-sm align-middle">
                <thead>
                    <tr>
                        <th>Contact</th>
                        <th>Rules</th>
                        <th>Assignments</th>
                        <th>Notes</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody id="deliverable-table-body">
                    @if($hasDeliverableRows)
                        @foreach($deliverableRows as $row)
                            @include('agreements.partials.deliverable-table-row', ['row' => $row])
                        @endforeach
                    @else
                        <tr class="deliverable-empty-row">
                            <td colspan="5" class="text-center text-muted py-4 small">
                                Click "+ Add Deliverable" to create a deliverable for this agreement.
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-end align-items-center mb-3">
            <button type="button" class="btn btn-sm btn-outline-primary" id="deliverable-add-button-bottom">
                + Add Deliverable
            </button>
        </div>

        <div id="deliverable-hidden-inputs">
            @foreach($deliverableRows as $row)
                @php $rowKey = $row['row_key']; @endphp
                <div data-deliverable-hidden-row="{{ $rowKey }}">
                    @if(!empty($row['id']))
                        <input type="hidden" name="deliverables[{{ $rowKey }}][id]" value="{{ $row['id'] }}">
                    @endif
                    <input type="hidden" name="deliverables[{{ $rowKey }}][_delete]" value="{{ $row['_delete'] ?? '0' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][contact_family_id]" value="{{ $row['contact_family_id'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][activity_type_id]" value="{{ $row['activity_type_id'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][sub_activity_id]" value="{{ $row['sub_activity_id'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][program_id]" value="{{ $row['program_id'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][metric_type]" value="{{ $row['metric_type'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][time_basis]" value="{{ $row['time_basis'] ?? 'observed' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][allotted_time_unit]" value="{{ $row['allotted_time_unit'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][contribution_basis]" value="{{ $row['contribution_basis'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][user_grouping_mode]" value="{{ $row['user_grouping_mode'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][include_additional_time]" value="{{ !empty($row['include_additional_time']) ? 1 : 0 }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][target_quantity]" value="{{ $row['target_quantity'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][suggested_due_date]" value="{{ $row['suggested_due_date'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][sort_order]" value="{{ $row['sort_order'] ?? 0 }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][notes]" value="{{ $row['notes'] ?? '' }}">
                    @foreach($row['user_ids'] ?? [] as $userId)
                        <input type="hidden" name="deliverables[{{ $rowKey }}][user_ids][]" value="{{ $userId }}">
                    @endforeach
                    @foreach($row['team_ids'] ?? [] as $teamId)
                        <input type="hidden" name="deliverables[{{ $rowKey }}][team_ids][]" value="{{ $teamId }}">
                    @endforeach
                    @foreach($row['user_assigned_at'] ?? [] as $userId => $assignedAt)
                        <input type="hidden" name="deliverables[{{ $rowKey }}][user_assigned_at][{{ $userId }}]" value="{{ $assignedAt }}">
                    @endforeach
                    @foreach($row['team_assigned_at'] ?? [] as $teamId => $assignedAt)
                        <input type="hidden" name="deliverables[{{ $rowKey }}][team_assigned_at][{{ $teamId }}]" value="{{ $assignedAt }}">
                    @endforeach
                    @foreach($row['user_targets'] ?? [] as $userId => $targetQuantity)
                        <input type="hidden" name="deliverables[{{ $rowKey }}][user_targets][{{ $userId }}]" value="{{ $targetQuantity }}">
                    @endforeach
                    @foreach($row['team_targets'] ?? [] as $teamId => $targetQuantity)
                        <input type="hidden" name="deliverables[{{ $rowKey }}][team_targets][{{ $teamId }}]" value="{{ $targetQuantity }}">
                    @endforeach
                </div>
            @endforeach
        </div>
</x-section-card>

<x-form-drawer id="deliverable-editor-drawer" title="Create Deliverable" title-id="deliverable-editor-drawer-title">
    <x-slot:summary>
        <div class="form-drawer-summary" id="deliverable-editor-summary">Choose a family to start.</div>
    </x-slot>

    @php
        $agreementDeliverablesConfig = [
            'userLookup' => $users->mapWithKeys(fn ($user) => [(string) $user->id => $user->name]),
            'entityBadgeClasses' => [
                'team' => \App\Support\EntityBadge::relationClasses('team'),
            ],
            'teamLookup' => $teams->pluck('name', 'id'),
            'contactFamilyLookup' => $contactFamilies->pluck('name', 'id'),
            'subActivityLookup' => $subActivities->pluck('name', 'id'),
            'subActivityMap' => $subActivityDurationMap,
            'activityTypeLookup' => $activityTypes->pluck('name', 'id'),
            'activityTypeDurationMap' => $activityTypes->mapWithKeys(fn ($type) => [(string) $type->id => [
                'hours' => (float) $type->duration_hours > 0 ? (float) $type->duration_hours : null,
                'days' => (float) $type->duration_days > 0 ? (float) $type->duration_days : null,
            ]]),
            'activityTypeContactFamilyMap' => $activityTypes->mapWithKeys(fn ($type) => [(string) $type->id => (string) $type->contact_family_id]),
            'programLookup' => $programLookup->map(fn ($p) => $p->name),
            'allProgramIds' => $allActiveProgramIds ?? [],
            'contactFamilyProgramMap' => $contactFamilies->mapWithKeys(fn ($family) => [(string) $family->id => $family->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all()]),
            'contactFamilyProgramScopeModeMap' => $contactFamilies->mapWithKeys(fn ($family) => [(string) $family->id => $family->program_scope_mode?->value ?? 'all']),
            'activityTypeProgramMap' => $activityTypes->mapWithKeys(fn ($type) => [(string) $type->id => $type->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all()]),
            'activityTypeProgramScopeModeMap' => $activityTypes->mapWithKeys(fn ($type) => [(string) $type->id => $type->program_scope_mode?->value ?? 'all']),
            'teamMembersMap' => $teams->mapWithKeys(fn ($team) => [(string) $team->id => $team->users->pluck('id')->map(fn ($id) => (string) $id)->values()->all()]),
        ];
    @endphp
    <script type="application/json" data-agreement-deliverables-config>@json($agreementDeliverablesConfig)</script>

    <input type="hidden" id="deliverable-editor-key" value="">
    <div data-deliverable-editor-fields>
        @include('agreements.partials.deliverable-fields', [
            'row' => $editorDefaults,
            'fieldPrefix' => 'deliverable_editor',
        ])
    </div>

    <x-slot:footer>
        <button type="button" class="btn btn-outline-secondary" id="deliverable-clear-button">Clear</button>
        <button type="button" class="btn btn-primary" id="deliverable-save-button">Save Deliverable</button>
    </x-slot>
</x-form-drawer>

@php
    $rawDeliverableRows = old('deliverables');
    $deliverableRows = [];
    $hasDeliverableRows = false;

    $programLookup = collect($projects ?? [])
        ->flatMap(fn ($project) => $project->programs ?? collect())
        ->merge($agreement?->programs ?? collect())
        ->unique('id')
        ->keyBy('id');

    $buildRulesSummary = function (array $row): string {
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
        if (!empty($row['user_grouping_mode'])) {
            $parts[] = ucfirst($row['user_grouping_mode']);
        }
        if (!empty($row['target_quantity'])) {
            $suffix = '';
            if (($row['metric_type'] ?? '') === 'time') {
                $activityType = collect($activityTypes ?? [])->firstWhere('id', $row['activity_type_id'] ?? null);
                if (($row['time_basis'] ?? 'observed') === 'allotted' && (float) ($activityType?->duration_days ?? 0) > 0) {
                    $suffix = ' days';
                } else {
                    $suffix = ' hrs';
                }
            }
            $parts[] = number_format((float) $row['target_quantity'], 1) . $suffix;
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
        $assignedUserIds = collect($row['user_ids'] ?? [])->map(fn ($id) => (int) $id);

        foreach ($row['team_ids'] ?? [] as $teamId) {
            $team = $teams->firstWhere('id', (int) $teamId);
            if (!$team) {
                continue;
            }

            if ($isJoint) {
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

    $enrichRow = function (array $row, string $rowKey) use ($contactFamilies, $activityTypes, $programLookup, $buildRulesSummary, $buildAssignmentGroups): array {
        return array_merge($row, [
            'row_key' => $rowKey,
            'contact_family_label' => $contactFamilies->firstWhere('id', $row['contact_family_id'] ?? null)?->name,
            'activity_type_label' => $activityTypes->firstWhere('id', $row['activity_type_id'] ?? null)?->name,
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
            'program_id' => $row['program_id'] ?? '',
            'metric_type' => $row['metric_type'] ?? '',
            'time_basis' => $row['time_basis'] ?? 'observed',
            'contribution_basis' => $row['contribution_basis'] ?? '',
            'user_grouping_mode' => $row['user_grouping_mode'] ?? '',
            'include_additional_time' => !empty($row['include_additional_time']),
            'target_quantity' => $row['target_quantity'] ?? '',
            'suggested_due_date' => $row['suggested_due_date'] ?? '',
            'sort_order' => $row['sort_order'] ?? 0,
            'notes' => $row['notes'] ?? '',
            'user_ids' => collect($row['user_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all(),
            'team_ids' => collect($row['team_ids'] ?? [])->map(fn ($id) => (int) $id)->values()->all(),
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
                'program_id' => $deliverable->program_id,
                'metric_type' => $deliverable->metric_type,
                'time_basis' => $deliverable->time_basis ?? 'observed',
                'contribution_basis' => $deliverable->contribution_basis,
                'user_grouping_mode' => $deliverable->user_grouping_mode,
                'include_additional_time' => (bool) $deliverable->include_additional_time,
                'target_quantity' => $deliverable->target_quantity,
                'suggested_due_date' => $deliverable->suggested_due_date?->format('Y-m-d') ?? '',
                'sort_order' => $deliverable->sort_order ?? 0,
                'notes' => $deliverable->notes ?? '',
                'user_ids' => $deliverable->users->filter(fn ($user) => !$user->pivot->unassigned_at)->pluck('id')->all(),
                'team_ids' => $deliverable->teams->filter(fn ($team) => !$team->pivot->unassigned_at)->pluck('id')->all(),
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
        'program_id' => '',
        'metric_type' => '',
        'time_basis' => 'observed',
        'contribution_basis' => '',
        'user_grouping_mode' => '',
        'include_additional_time' => false,
        'target_quantity' => '',
        'suggested_due_date' => '',
        'sort_order' => 0,
        'notes' => '',
        'user_ids' => [],
        'team_ids' => [],
        'classification_locked' => false,
        'semantic_locked' => false,
    ];
@endphp

<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
            <div>
                <h5 class="mb-1">Deliverables</h5>
                <p class="text-muted small mb-0">Use the table to manage rows. Each deliverable carries classification, counting rules, and assignments.</p>
            </div>
        </div>

        <div class="table-responsive mb-3">
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

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="form-text mb-0">Rows are staged locally and saved only when the agreement form is submitted.</div>
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
                    <input type="hidden" name="deliverables[{{ $rowKey }}][program_id]" value="{{ $row['program_id'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][metric_type]" value="{{ $row['metric_type'] ?? '' }}">
                    <input type="hidden" name="deliverables[{{ $rowKey }}][time_basis]" value="{{ $row['time_basis'] ?? 'observed' }}">
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
                </div>
            @endforeach
        </div>

        <div class="modal fade" id="deliverable-editor-modal" tabindex="-1" aria-labelledby="deliverable-editor-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title mb-0" id="deliverable-editor-modal-label">Deliverable Form</h5>
                            <div class="text-muted small">Add a new deliverable or edit the selected row, then save it into the table.</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" id="deliverable-editor-key" value="">
                        <div data-deliverable-editor-fields>
                            @include('agreements.partials.deliverable-fields', [
                                'row' => $editorDefaults,
                                'fieldPrefix' => 'deliverable_editor',
                            ])
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" id="deliverable-clear-button">Clear</button>
                        <button type="button" class="btn btn-primary" id="deliverable-save-button">Save Deliverable</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
<script>
(function () {
    document.addEventListener('DOMContentLoaded', function () {
        const tableBody = document.getElementById('deliverable-table-body');
        const hiddenInputs = document.getElementById('deliverable-hidden-inputs');
        const addButtons = [document.getElementById('deliverable-add-button-bottom')].filter(Boolean);
        const saveButton = document.getElementById('deliverable-save-button');
        const clearButton = document.getElementById('deliverable-clear-button');
        const editorKeyInput = document.getElementById('deliverable-editor-key');
        const editorModalEl = document.getElementById('deliverable-editor-modal');
        const editorCard = editorModalEl ? editorModalEl.querySelector('.modal-content') : null;
        const editorFieldset = editorModalEl ? editorModalEl.querySelector('[data-deliverable-fields]') : null;

        if (!tableBody || !hiddenInputs || !saveButton || !clearButton || !editorKeyInput || !editorFieldset) {
            return;
        }

        const userLookup = @json($users->pluck('name', 'id'));
        const teamLookup = @json($teams->pluck('name', 'id'));
        const contactFamilyLookup = @json($contactFamilies->pluck('name', 'id'));
        const activityTypeLookup = @json($activityTypes->pluck('name', 'id'));
        const activityTypeDurationMap = @json($activityTypes->mapWithKeys(fn ($type) => [(string) $type->id => [
            'hours' => (float) $type->duration_hours > 0 ? (float) $type->duration_hours : null,
            'days' => (float) $type->duration_days > 0 ? (float) $type->duration_days : null,
        ]]));
        const programLookup = @json($programLookup->map(fn ($p) => $p->name));
        const contactFamilyProgramMap = @json($contactFamilies->mapWithKeys(fn ($family) => [(string) $family->id => $family->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all()]));
        const activityTypeProgramMap = @json($activityTypes->mapWithKeys(fn ($type) => [(string) $type->id => $type->programs->pluck('id')->map(fn ($id) => (string) $id)->values()->all()]));
        const teamMembersMap = @json($teams->mapWithKeys(fn ($team) => [(string) $team->id => $team->users->pluck('id')->map(fn ($id) => (string) $id)->values()->all()]));
        const agreementTeamPickerId = 'agreement-{{ $agreement ? 'edit' : 'create' }}-teams';
        const agreementUserPickerId = 'agreement-{{ $agreement ? 'edit' : 'create' }}-users';

        let currentKey = null;
        let nextTempId = 1;
        const rowStore = {};
        const editorModal = window.bootstrap ? bootstrap.Modal.getOrCreateInstance(editorModalEl) : null;

        function selectedProgramIds() {
            const picker = document.getElementById('agreement-scope-programs');
            if (!picker) return [];
            return Array.from(picker.querySelectorAll('[data-token-inputs] input')).map(function (input) {
                return String(input.value);
            });
        }

        function selectedIdsFromPicker(pickerId) {
            const picker = document.getElementById(pickerId);
            if (!picker) return [];
            return Array.from(picker.querySelectorAll('[data-token-inputs] input')).map(function (input) {
                return String(input.value);
            });
        }

        function isAllowedByPrograms(programIds, allowGlobal, activeProgramIds) {
            const normalizedProgramIds = Array.isArray(programIds) ? programIds.map(String) : [];
            const selectedPrograms = new Set((activeProgramIds || []).map(String));
            if (normalizedProgramIds.length === 0) return allowGlobal;
            if (selectedPrograms.size === 0) return false;
            return normalizedProgramIds.some(function (programId) {
                return selectedPrograms.has(String(programId));
            });
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function newRowKey() {
            return 'row-new-' + Date.now() + '-' + (nextTempId++);
        }

        function buildRulesSummary(rowData) {
            const parts = [];
            if (rowData.metric_type) {
                if (rowData.metric_type === 'time') {
                    parts.push((rowData.time_basis || 'observed') === 'allotted' ? 'Allotted time' : 'Time');
                } else {
                    parts.push(rowData.metric_type.charAt(0).toUpperCase() + rowData.metric_type.slice(1));
                }
            }
            if (rowData.contribution_basis) parts.push(rowData.contribution_basis.charAt(0).toUpperCase() + rowData.contribution_basis.slice(1));
            if (rowData.user_grouping_mode) parts.push(rowData.user_grouping_mode.charAt(0).toUpperCase() + rowData.user_grouping_mode.slice(1));
            if (rowData.target_quantity) {
                let suffix = '';
                if (rowData.metric_type === 'time') {
                    const duration = activityTypeDurationMap[String(rowData.activity_type_id || '')] || {};
                    suffix = (rowData.time_basis || 'observed') === 'allotted' && duration.days ? ' days' : ' hrs';
                }
                parts.push(parseFloat(rowData.target_quantity).toFixed(1) + suffix);
            }
            if (rowData.include_additional_time) parts.push('Incl. prep/follow up');
            return parts.join(' · ');
        }

        function selectedActivityTypeDuration() {
            const typeSelect = editorFieldset.querySelector('[data-deliverable-activity-type]');
            const selectedOption = typeSelect?.options[typeSelect.selectedIndex];
            const typeId = typeSelect?.value || '';

            if (selectedOption && selectedOption.value) {
                const hours = parseFloat(selectedOption.dataset.durationHours || '');
                const days = parseFloat(selectedOption.dataset.durationDays || '');

                return {
                    hours: Number.isFinite(hours) && hours > 0 ? hours : null,
                    days: Number.isFinite(days) && days > 0 ? days : null,
                };
            }

            const mapped = activityTypeDurationMap[String(typeId)] || {};

            return {
                hours: mapped.hours || null,
                days: mapped.days || null,
            };
        }

        function activityTypeHasDuration() {
            const duration = selectedActivityTypeDuration();

            return !!(duration.hours || duration.days);
        }

        function formatDurationReminder(duration) {
            if (duration.days) {
                const value = parseFloat(duration.days);
                const label = value === 1 ? 'day' : 'days';

                return 'Each completion: ' + value + ' ' + label;
            }

            if (duration.hours) {
                const value = parseFloat(duration.hours);
                const label = value === 1 ? 'hour' : 'hours';

                return 'Each completion: ' + value + ' ' + label;
            }

            return '';
        }

        function buildAssignmentGroups(rowData) {
            const groups = [];
            const groupedUserIds = new Set();
            const isJoint = rowData.user_grouping_mode === 'joint';
            const assignedUserIds = (rowData.user_ids || []).map(String);

            (rowData.team_ids || []).forEach(function (teamId) {
                const teamName = teamLookup[teamId];
                const memberIds = teamMembersMap[teamId] || [];
                if (!teamName) return;

                let teamUserNames = [];
                if (isJoint) {
                    teamUserNames = memberIds.map(function (memberId) {
                        groupedUserIds.add(String(memberId));
                        return userLookup[memberId];
                    }).filter(Boolean).sort();
                } else {
                    teamUserNames = assignedUserIds
                        .filter(function (userId) { return memberIds.map(String).includes(String(userId)); })
                        .map(function (userId) {
                            groupedUserIds.add(String(userId));
                            return userLookup[userId];
                        })
                        .filter(Boolean)
                        .sort();
                }

                groups.push({ team_name: teamName, users: teamUserNames });
            });

            const standaloneUsers = assignedUserIds
                .filter(function (userId) { return !groupedUserIds.has(String(userId)); })
                .map(function (userId) { return userLookup[userId]; })
                .filter(Boolean)
                .sort();

            if (standaloneUsers.length > 0) {
                groups.push({ team_name: null, users: standaloneUsers });
            }

            return groups;
        }

        function renderAssignmentGroups(groups) {
            if (!groups || groups.length === 0) {
                return '<span class="text-muted small">—</span>';
            }

            return groups.map(function (group) {
                let html = '<div class="mb-2 w-100">';
                if (group.team_name) {
                    html += '<div class="d-block mb-1"><span class="badge bg-secondary-subtle text-secondary-emphasis border">' + escapeHtml(group.team_name) + '</span></div>';
                    if (group.users.length > 0) {
                        html += '<div class="ps-2 d-flex flex-column align-items-start gap-1">';
                        html += group.users.map(function (name) {
                            return '<span class="badge bg-primary-subtle text-primary-emphasis border">' + escapeHtml(name) + '</span>';
                        }).join('');
                        html += '</div>';
                    }
                } else if (group.users.length > 0) {
                    html += '<div class="d-flex flex-column align-items-start gap-1">';
                    html += group.users.map(function (name) {
                        return '<span class="badge bg-primary-subtle text-primary-emphasis border">' + escapeHtml(name) + '</span>';
                    }).join('');
                    html += '</div>';
                }
                html += '</div>';
                return html;
            }).join('');
        }

        function enrichRowData(rowData) {
            return Object.assign({}, rowData, {
                contact_family_label: contactFamilyLookup[rowData.contact_family_id] || '',
                activity_type_label: activityTypeLookup[rowData.activity_type_id] || '',
                program_label: programLookup[rowData.program_id] || '',
                rules_summary: buildRulesSummary(rowData),
                assignment_groups: buildAssignmentGroups(rowData),
            });
        }

        function emptyStateRowMarkup() {
            return '<tr class="deliverable-empty-row"><td colspan="5" class="text-center text-muted py-4 small">Click "+ Add Deliverable" to create a deliverable for this agreement.</td></tr>';
        }

        function hasVisibleDeliverableRows() {
            return Array.from(tableBody.querySelectorAll('[data-deliverable-row]')).some(function (row) {
                return window.getComputedStyle(row).display !== 'none';
            });
        }

        function renderEmptyStateIfNeeded() {
            const emptyRow = tableBody.querySelector('.deliverable-empty-row');
            if (hasVisibleDeliverableRows()) {
                if (emptyRow) emptyRow.remove();
                return;
            }
            if (!emptyRow) tableBody.insertAdjacentHTML('beforeend', emptyStateRowMarkup());
        }

        function getAgreementMembershipPool() {
            const selectedTeamIds = selectedIdsFromPicker(agreementTeamPickerId);
            const selectedUserIds = selectedIdsFromPicker(agreementUserPickerId);
            const teamMemberIds = new Set();
            selectedTeamIds.forEach(function (teamId) {
                (teamMembersMap[teamId] || []).forEach(function (memberId) {
                    teamMemberIds.add(String(memberId));
                });
            });
            const directUserIds = selectedUserIds.filter(function (userId) {
                return !teamMemberIds.has(String(userId));
            });

            return { selectedTeamIds, directUserIds, teamMemberIds };
        }

        function getSelectedAssignmentState() {
            const userIds = [];
            const teamIds = [];
            editorFieldset.querySelectorAll('[data-deliverable-team-checkbox]:checked').forEach(function (cb) {
                teamIds.push(cb.value);
            });
            editorFieldset.querySelectorAll('[data-deliverable-user-checkbox]:checked').forEach(function (cb) {
                userIds.push(cb.value);
            });
            return { user_ids: userIds, team_ids: teamIds };
        }

        function renderAssignmentLedger(selectedUserIds, selectedTeamIds) {
            const ledger = editorFieldset.querySelector('[data-deliverable-assignment-ledger]');
            const selectAllBtn = editorFieldset.querySelector('[data-deliverable-select-all]');
            if (!ledger) return;

            const pool = getAgreementMembershipPool();
            const selectedUsers = new Set((selectedUserIds || []).map(String));
            const selectedTeams = new Set((selectedTeamIds || []).map(String));
            const grouping = editorFieldset.querySelector('[data-deliverable-grouping]:checked')?.value || '';
            const basis = editorFieldset.querySelector('[data-deliverable-basis]:checked')?.value || '';
            const isIndividual = grouping === 'individual';

            ledger.innerHTML = '';

            if (basis !== 'user' || (pool.selectedTeamIds.length === 0 && pool.directUserIds.length === 0)) {
                const empty = document.createElement('div');
                empty.className = 'text-muted small py-2';
                empty.setAttribute('data-deliverable-assignment-empty', '');
                empty.textContent = 'Add teams or users to the agreement above before assigning this deliverable.';
                ledger.appendChild(empty);
                if (selectAllBtn) selectAllBtn.classList.add('d-none');
                return;
            }

            if (selectAllBtn) selectAllBtn.classList.remove('d-none');

            function createCheckbox(type, value, checked) {
                const input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'form-check-input';
                input.value = value;
                input.checked = checked;
                if (type === 'team') input.setAttribute('data-deliverable-team-checkbox', '');
                if (type === 'user') input.setAttribute('data-deliverable-user-checkbox', '');
                return input;
            }

            function bindMemberCheckbox(teamCheckbox, memberCheckbox) {
                memberCheckbox.addEventListener('change', function () {
                    if (teamCheckbox && !memberCheckbox.checked) {
                        teamCheckbox.checked = false;
                    }
                });
            }

            function renderTeamCard(teamId, memberIds) {
                const card = document.createElement('div');
                card.className = 'border rounded overflow-hidden bg-body mb-2';

                const header = document.createElement('div');
                header.className = 'd-flex align-items-center gap-2 px-2 py-1 bg-light';
                const teamLabel = document.createElement('span');
                teamLabel.className = 'fw-semibold small';
                teamLabel.textContent = teamLookup[teamId] || ('Team ' + teamId);

                let teamCheckbox = null;
                if (!isIndividual) {
                    teamCheckbox = createCheckbox('team', teamId, selectedTeams.has(String(teamId)));
                    header.appendChild(teamCheckbox);
                }
                header.appendChild(teamLabel);
                card.appendChild(header);

                const memberCheckboxes = [];
                memberIds.forEach(function (memberId) {
                    const row = document.createElement('div');
                    row.className = 'd-flex align-items-center gap-2 py-1 px-2 border-top ps-3';
                    const userCheckbox = createCheckbox('user', memberId, selectedUsers.has(String(memberId)));
                    const userLabel = document.createElement('span');
                    userLabel.className = 'small text-muted';
                    userLabel.textContent = userLookup[memberId] || ('User ' + memberId);
                    row.appendChild(userCheckbox);
                    row.appendChild(userLabel);
                    card.appendChild(row);
                    memberCheckboxes.push(userCheckbox);
                    bindMemberCheckbox(teamCheckbox, userCheckbox);
                });

                if (teamCheckbox) {
                    bindTeamCheckbox(teamCheckbox, memberCheckboxes);
                }

                ledger.appendChild(card);
            }

            function bindTeamCheckbox(teamCheckbox, memberCheckboxes) {
                teamCheckbox.addEventListener('change', function () {
                    if (teamCheckbox.checked) {
                        memberCheckboxes.forEach(function (cb) { cb.checked = true; });
                    }
                });
            }

            pool.selectedTeamIds.forEach(function (teamId) {
                renderTeamCard(teamId, teamMembersMap[teamId] || []);
            });

            if (pool.directUserIds.length > 0) {
                const card = document.createElement('div');
                card.className = 'border rounded overflow-hidden bg-body';
                const header = document.createElement('div');
                header.className = 'px-2 py-1 bg-light';
                header.innerHTML = '<div class="fw-semibold small">Additional users</div>';
                card.appendChild(header);

                pool.directUserIds.forEach(function (userId) {
                    const row = document.createElement('div');
                    row.className = 'd-flex align-items-center gap-2 py-1 px-2 border-top';
                    const userCheckbox = createCheckbox('user', userId, selectedUsers.has(String(userId)));
                    const userLabel = document.createElement('span');
                    userLabel.className = 'small';
                    userLabel.textContent = userLookup[userId] || ('User ' + userId);
                    row.appendChild(userCheckbox);
                    row.appendChild(userLabel);
                    card.appendChild(row);
                });
                ledger.appendChild(card);
            }
        }

        function syncProgramFilterOptions() {
            const select = editorFieldset.querySelector('[data-deliverable-program]');
            if (!select) return;
            const currentValue = select.value;
            const activeProgramIds = selectedProgramIds();
            select.innerHTML = '<option value="">Any selected agreement program</option>';
            activeProgramIds.forEach(function (programId) {
                const option = document.createElement('option');
                option.value = programId;
                option.textContent = programLookup[programId] || ('Program ' + programId);
                if (currentValue === programId) option.selected = true;
                select.appendChild(option);
            });
            if (currentValue && !activeProgramIds.includes(currentValue)) {
                select.value = '';
            }
        }

        function syncActivityTypeOptions() {
            const contactFamilySelect = editorFieldset.querySelector('[data-deliverable-contact-family]');
            const activityTypeSelect = editorFieldset.querySelector('[data-deliverable-activity-type]');
            if (!contactFamilySelect || !activityTypeSelect) return;

            const activeProgramIds = selectedProgramIds();
            const currentValue = activityTypeSelect.value;

            Array.from(contactFamilySelect.options).forEach(function (option) {
                if (!option.value) { option.hidden = false; option.disabled = false; return; }
                const programIds = JSON.parse(option.dataset.programIds || '[]');
                const visible = isAllowedByPrograms(programIds, true, activeProgramIds);
                option.hidden = !visible;
                option.disabled = !visible;
            });

            if (contactFamilySelect.value) {
                const selectedFamilyOption = contactFamilySelect.querySelector('option[value="' + CSS.escape(contactFamilySelect.value) + '"]');
                if (!selectedFamilyOption || selectedFamilyOption.hidden) contactFamilySelect.value = '';
            }

            Array.from(activityTypeSelect.options).forEach(function (option) {
                if (!option.value) { option.hidden = false; option.disabled = false; return; }
                const matchesFamily = !contactFamilySelect.value || option.dataset.contactFamilyId === contactFamilySelect.value;
                const programIds = JSON.parse(option.dataset.programIds || '[]');
                const matchesPrograms = isAllowedByPrograms(programIds, true, activeProgramIds);
                const matches = matchesFamily && matchesPrograms;
                option.hidden = !matches;
                option.disabled = !matches;
            });

            if (currentValue) {
                const selectedOption = activityTypeSelect.querySelector('option[value="' + CSS.escape(currentValue) + '"]');
                if (!selectedOption || selectedOption.hidden) activityTypeSelect.value = '';
            }

            syncProgramFilterOptions();
            syncEditorVisibility();
        }

        function syncTargetLabels() {
            const metric = editorFieldset.querySelector('[data-deliverable-metric]:checked')?.value || '';
            const timeBasis = editorFieldset.querySelector('[data-deliverable-time-basis]:checked')?.value || 'observed';
            const duration = selectedActivityTypeDuration();
            let labelText = 'Target Hours';

            if (metric === 'completion') {
                labelText = 'Target Completions';
            } else if (timeBasis === 'allotted' && duration.days) {
                labelText = 'Target Days';
            }

            editorFieldset.querySelectorAll('[data-deliverable-target-label], [data-deliverable-target-label-locked]').forEach(function (el) {
                el.innerHTML = labelText + ' <span class="text-danger">*</span>';
            });

            const reminder = editorFieldset.querySelector('[data-deliverable-duration-reminder]');
            if (reminder) {
                const reminderText = metric === 'time' && timeBasis === 'allotted' ? formatDurationReminder(duration) : '';
                reminder.textContent = reminderText;
                reminder.classList.toggle('d-none', !reminderText);
            }
        }

        function syncTimeBasisState() {
            const metric = editorFieldset.querySelector('[data-deliverable-metric]:checked')?.value || '';
            const timeBasisWrapper = editorFieldset.querySelector('[data-time-basis-wrapper]');
            const timeBasisInputs = editorFieldset.querySelectorAll('[data-deliverable-time-basis]');
            const isTimeMetric = metric === 'time';
            const hasDuration = activityTypeHasDuration();

            if (timeBasisWrapper) {
                timeBasisWrapper.classList.toggle('opacity-50', !isTimeMetric);
            }

            timeBasisInputs.forEach(function (input) {
                const isAllotted = input.value === 'allotted';
                input.disabled = !isTimeMetric || (isAllotted && !hasDuration);
                input.closest('label')?.classList.toggle('text-muted', input.disabled);
            });

            const checkedBasis = editorFieldset.querySelector('[data-deliverable-time-basis]:checked');
            if (isTimeMetric && checkedBasis?.value === 'allotted' && !hasDuration) {
                const observedInput = editorFieldset.querySelector('[data-deliverable-time-basis][value="observed"]');
                if (observedInput) {
                    observedInput.checked = true;
                }
            }

            if (!isTimeMetric) {
                const observedInput = editorFieldset.querySelector('[data-deliverable-time-basis][value="observed"]');
                if (observedInput) {
                    observedInput.checked = true;
                }
            }
        }

        function getTargetInput() {
            const readonlyBlock = editorFieldset.querySelector('[data-deliverable-requirement-readonly]');
            if (readonlyBlock && !readonlyBlock.classList.contains('d-none')) {
                return editorFieldset.querySelector('[data-deliverable-target-locked]');
            }
            return editorFieldset.querySelector('[data-deliverable-target]');
        }

        function syncEditorVisibility() {
            const basis = editorFieldset.querySelector('[data-deliverable-basis]:checked')?.value || '';
            const metric = editorFieldset.querySelector('[data-deliverable-metric]:checked')?.value || '';
            const timeBasis = editorFieldset.querySelector('[data-deliverable-time-basis]:checked')?.value || 'observed';
            const familySelect = editorFieldset.querySelector('[data-deliverable-contact-family]');
            const selectedOption = familySelect?.options[familySelect.selectedIndex];
            const tracksAdditionalTime = selectedOption?.dataset.trackAdditionalTime === '1';

            const groupingWrapper = editorFieldset.querySelector('[data-grouping-wrapper]');
            const assignmentWrapper = editorFieldset.querySelector('[data-user-assignment-wrapper]');
            const metricDetailsWrapper = editorFieldset.querySelector('[data-metric-details-wrapper]');
            const additionalTimeWrapper = editorFieldset.querySelector('[data-additional-time-wrapper]');
            const additionalTimeCheckbox = editorFieldset.querySelector('[data-deliverable-additional-time]');
            const additionalTimeMessage = editorFieldset.querySelector('[data-additional-time-message]');
            const hasContactFamily = !!(familySelect && familySelect.value);
            const familyName = hasContactFamily
                ? (selectedOption?.textContent?.trim() || contactFamilyLookup[familySelect.value] || 'selected')
                : '';

            syncTimeBasisState();

            if (groupingWrapper) groupingWrapper.classList.toggle('d-none', basis !== 'user');
            if (assignmentWrapper) assignmentWrapper.classList.toggle('d-none', basis !== 'user');
            if (metricDetailsWrapper) metricDetailsWrapper.classList.toggle('d-none', metric !== 'time' && metric !== 'completion');

            const showAdditionalTime = metric === 'time' && timeBasis === 'observed' && tracksAdditionalTime && hasContactFamily;
            if (additionalTimeWrapper) additionalTimeWrapper.classList.toggle('d-none', !showAdditionalTime);

            if (additionalTimeMessage && showAdditionalTime) {
                additionalTimeMessage.textContent = 'The ' + familyName + ' contact family requires prep and follow up time to be reported in activity logging. Should this time contribute to deliverable progress?';
            }

            if (additionalTimeCheckbox && !showAdditionalTime) {
                additionalTimeCheckbox.checked = false;
            }

            syncTargetLabels();

            const assignment = getSelectedAssignmentState();
            renderAssignmentLedger(assignment.user_ids, assignment.team_ids);
        }

        function applyLockState(rowData) {
            const classificationLocked = !!rowData.classification_locked;
            const semanticLocked = !!rowData.semantic_locked;

            editorFieldset.querySelector('[data-deliverable-classification-lock-notice]')?.classList.toggle('d-none', !classificationLocked);
            editorFieldset.querySelector('[data-deliverable-semantic-lock-notice]')?.classList.toggle('d-none', !semanticLocked);
            editorFieldset.querySelector('[data-deliverable-classification-editor]')?.classList.toggle('d-none', classificationLocked);
            editorFieldset.querySelector('[data-deliverable-classification-readonly]')?.classList.toggle('d-none', !classificationLocked);
            editorFieldset.querySelector('[data-deliverable-requirement-editor]')?.classList.toggle('d-none', semanticLocked);
            editorFieldset.querySelector('[data-deliverable-requirement-readonly]')?.classList.toggle('d-none', !semanticLocked);

            if (classificationLocked) {
                editorFieldset.querySelector('[data-readonly-contact-family]').textContent = contactFamilyLookup[rowData.contact_family_id] || '—';
                editorFieldset.querySelector('[data-readonly-activity-type]').textContent = activityTypeLookup[rowData.activity_type_id] || 'Any activity type';
                editorFieldset.querySelector('[data-readonly-program]').textContent = programLookup[rowData.program_id] || 'Any selected agreement program';
            }

            if (semanticLocked) {
                const basisLabels = { contact: 'By Contact', user: 'By User' };
                const groupingLabels = { joint: 'Joint', individual: 'Individual' };
                let metricLabel = '—';
                if (rowData.metric_type === 'time') {
                    metricLabel = (rowData.time_basis || 'observed') === 'allotted' ? 'Allotted time' : 'Time';
                } else if (rowData.metric_type) {
                    metricLabel = rowData.metric_type.charAt(0).toUpperCase() + rowData.metric_type.slice(1);
                }
                editorFieldset.querySelector('[data-readonly-metric]').textContent = metricLabel;
                editorFieldset.querySelector('[data-readonly-basis]').textContent = basisLabels[rowData.contribution_basis] || '—';
                editorFieldset.querySelector('[data-readonly-grouping]').textContent = groupingLabels[rowData.user_grouping_mode] || '—';
                editorFieldset.querySelector('[data-readonly-additional-time]').textContent = rowData.include_additional_time ? 'Yes' : 'No';
            }

            editorFieldset.querySelector('[data-deliverable-target]')?.toggleAttribute('required', !semanticLocked);
            editorFieldset.querySelector('[data-deliverable-target-locked]')?.toggleAttribute('required', semanticLocked);
        }

        function collectEditorData() {
            const fieldPrefix = 'deliverable_editor';
            const basis = editorFieldset.querySelector('[data-deliverable-basis]:checked')?.value || '';
            const assignment = getSelectedAssignmentState();

            function fieldValue(selector, fallback) {
                const el = editorFieldset.querySelector(selector);
                if (!el) return fallback || '';
                if (el.type === 'checkbox') return el.checked;
                return el.value || '';
            }

            const targetInput = getTargetInput();

            const rowData = {
                id: editorCard.querySelector('[name="deliverable_editor[id]"]')?.value || '',
                _delete: '0',
                contact_family_id: fieldValue('[data-deliverable-contact-family]', ''),
                activity_type_id: fieldValue('[data-deliverable-activity-type]', ''),
                program_id: fieldValue('[data-deliverable-program]', ''),
                metric_type: editorFieldset.querySelector('[data-deliverable-metric]:checked')?.value || '',
                time_basis: editorFieldset.querySelector('[data-deliverable-time-basis]:checked')?.value || 'observed',
                contribution_basis: basis,
                user_grouping_mode: editorFieldset.querySelector('[data-deliverable-grouping]:checked')?.value || '',
                include_additional_time: !!editorFieldset.querySelector('[data-deliverable-additional-time]')?.checked,
                target_quantity: targetInput ? targetInput.value : '',
                suggested_due_date: fieldValue('[data-deliverable-due-date]', ''),
                sort_order: 0,
                notes: fieldValue('[data-deliverable-notes]', ''),
                user_ids: basis === 'user' ? assignment.user_ids : [],
                team_ids: basis === 'user' && editorFieldset.querySelector('[data-deliverable-grouping]:checked')?.value === 'joint' ? assignment.team_ids : [],
                classification_locked: !!rowStore[currentKey]?.classification_locked,
                semantic_locked: !!rowStore[currentKey]?.semantic_locked,
            };

            if (rowData.classification_locked && rowStore[currentKey]) {
                rowData.contact_family_id = rowStore[currentKey].contact_family_id;
                rowData.activity_type_id = rowStore[currentKey].activity_type_id;
                rowData.program_id = rowStore[currentKey].program_id;
            }
            if (rowData.semantic_locked && rowStore[currentKey]) {
                rowData.metric_type = rowStore[currentKey].metric_type;
                rowData.time_basis = rowStore[currentKey].time_basis;
                rowData.contribution_basis = rowStore[currentKey].contribution_basis;
                rowData.user_grouping_mode = rowStore[currentKey].user_grouping_mode;
                rowData.include_additional_time = rowStore[currentKey].include_additional_time;
            }

            return enrichRowData(rowData);
        }

        function setEditorData(rowKey, rowData) {
            currentKey = rowKey;
            editorKeyInput.value = rowKey || '';
            const fieldPrefix = 'deliverable_editor';

            editorCard.querySelector('[name="deliverable_editor[id]"]')?.remove();
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = fieldPrefix + '[id]';
            idInput.value = rowData.id || '';
            editorFieldset.prepend(idInput);

            const familySelect = editorFieldset.querySelector('[data-deliverable-contact-family]');
            if (familySelect) familySelect.value = rowData.contact_family_id || '';
            const typeSelect = editorFieldset.querySelector('[data-deliverable-activity-type]');
            if (typeSelect) typeSelect.value = rowData.activity_type_id || '';

            syncActivityTypeOptions();
            const programSelect = editorFieldset.querySelector('[data-deliverable-program]');
            if (programSelect) programSelect.value = rowData.program_id || '';

            editorFieldset.querySelectorAll('[data-deliverable-metric]').forEach(function (radio) {
                radio.checked = radio.value === (rowData.metric_type || '');
            });
            editorFieldset.querySelectorAll('[data-deliverable-time-basis]').forEach(function (radio) {
                radio.checked = radio.value === (rowData.time_basis || 'observed');
            });
            editorFieldset.querySelectorAll('[data-deliverable-basis]').forEach(function (radio) {
                radio.checked = radio.value === (rowData.contribution_basis || '');
            });
            editorFieldset.querySelectorAll('[data-deliverable-grouping]').forEach(function (radio) {
                radio.checked = radio.value === (rowData.user_grouping_mode || '');
            });

            const targetInput = getTargetInput();
            if (targetInput) targetInput.value = rowData.target_quantity || '';
            editorFieldset.querySelectorAll('[data-deliverable-target], [data-deliverable-target-locked]').forEach(function (input) {
                if (input !== targetInput) input.value = rowData.target_quantity || '';
            });
            const dueInput = editorFieldset.querySelector('[data-deliverable-due-date]');
            if (dueInput) dueInput.value = rowData.suggested_due_date || '';
            const notesInput = editorFieldset.querySelector('[data-deliverable-notes]');
            if (notesInput) notesInput.value = rowData.notes || '';
            const additionalTime = editorFieldset.querySelector('[data-deliverable-additional-time]');
            if (additionalTime) additionalTime.checked = !!rowData.include_additional_time;

            applyLockState(rowData);
            syncEditorVisibility();
            renderAssignmentLedger(rowData.user_ids || [], rowData.team_ids || []);

            if (editorModal) editorModal.show();
        }

        function clearEditor(showModal) {
            currentKey = null;
            editorKeyInput.value = '';
            editorCard.querySelector('[name="deliverable_editor[id]"]')?.remove();

            const familySelect = editorFieldset.querySelector('[data-deliverable-contact-family]');
            if (familySelect) familySelect.value = '';
            const typeSelect = editorFieldset.querySelector('[data-deliverable-activity-type]');
            if (typeSelect) typeSelect.value = '';
            const programSelect = editorFieldset.querySelector('[data-deliverable-program]');
            if (programSelect) programSelect.value = '';

            editorFieldset.querySelectorAll('[data-deliverable-metric], [data-deliverable-time-basis], [data-deliverable-basis], [data-deliverable-grouping]').forEach(function (radio) {
                radio.checked = false;
            });
            editorFieldset.querySelectorAll('[data-deliverable-target], [data-deliverable-target-locked], [data-deliverable-due-date], [data-deliverable-notes]').forEach(function (input) {
                input.value = '';
            });
            const additionalTime = editorFieldset.querySelector('[data-deliverable-additional-time]');
            if (additionalTime) additionalTime.checked = false;

            applyLockState({ classification_locked: false, semantic_locked: false });
            syncActivityTypeOptions();
            renderAssignmentLedger([], []);

            if (showModal !== false && editorModal) editorModal.show();
        }

        function readHiddenRowData(hiddenRow) {
            const rowKey = hiddenRow.dataset.deliverableHiddenRow;
            function findValue(field) {
                return hiddenRow.querySelector('input[name="deliverables[' + rowKey + '][' + field + ']"]')?.value || '';
            }
            function findCheckedArray(field) {
                return Array.from(hiddenRow.querySelectorAll('input[name="deliverables[' + rowKey + '][' + field + '][]"]')).map(function (input) {
                    return input.value;
                });
            }

            const stored = rowStore[rowKey] || {};
            return enrichRowData({
                row_key: rowKey,
                id: findValue('id'),
                _delete: findValue('_delete') || '0',
                contact_family_id: findValue('contact_family_id'),
                activity_type_id: findValue('activity_type_id'),
                program_id: findValue('program_id'),
                metric_type: findValue('metric_type'),
                time_basis: findValue('time_basis') || 'observed',
                contribution_basis: findValue('contribution_basis'),
                user_grouping_mode: findValue('user_grouping_mode'),
                include_additional_time: findValue('include_additional_time') === '1',
                target_quantity: findValue('target_quantity'),
                suggested_due_date: findValue('suggested_due_date'),
                sort_order: findValue('sort_order') || 0,
                notes: findValue('notes'),
                user_ids: findCheckedArray('user_ids'),
                team_ids: findCheckedArray('team_ids'),
                classification_locked: !!stored.classification_locked,
                semantic_locked: !!stored.semantic_locked,
            });
        }

        function getAllowedMembershipIds() {
            const pool = getAgreementMembershipPool();
            const allowedUserIds = new Set(pool.directUserIds.map(String));
            pool.selectedTeamIds.forEach(function (teamId) {
                (teamMembersMap[teamId] || []).forEach(function (memberId) {
                    allowedUserIds.add(String(memberId));
                });
            });
            return {
                allowedUserIds: allowedUserIds,
                allowedTeamIds: new Set(pool.selectedTeamIds.map(String)),
            };
        }

        function syncStoredRowsToScope() {
            const activeProgramIds = selectedProgramIds();
            const membership = getAllowedMembershipIds();

            Array.from(hiddenInputs.querySelectorAll('[data-deliverable-hidden-row]')).forEach(function (hiddenRow) {
                const rowData = readHiddenRowData(hiddenRow);
                const rowKey = rowData.row_key;
                if (!rowKey || rowData._delete === '1') return;

                const familyAllowed = !rowData.contact_family_id || isAllowedByPrograms(contactFamilyProgramMap[String(rowData.contact_family_id)] || [], true, activeProgramIds);
                const typeAllowed = !rowData.activity_type_id || isAllowedByPrograms(activityTypeProgramMap[String(rowData.activity_type_id)] || [], true, activeProgramIds);
                const programAllowed = !rowData.program_id || activeProgramIds.includes(String(rowData.program_id));

                if (!familyAllowed || !typeAllowed || !programAllowed) {
                    if (rowData.id) markRowDeleted(rowKey);
                    else deleteRow(rowKey);
                    delete rowStore[rowKey];
                    return;
                }

                const filteredUserIds = (rowData.user_ids || []).filter(function (userId) {
                    return membership.allowedUserIds.has(String(userId));
                });
                const filteredTeamIds = (rowData.team_ids || []).filter(function (teamId) {
                    return membership.allowedTeamIds.has(String(teamId));
                });

                if (filteredUserIds.length !== (rowData.user_ids || []).length || filteredTeamIds.length !== (rowData.team_ids || []).length) {
                    rowData.user_ids = filteredUserIds;
                    rowData.team_ids = filteredTeamIds;
                    const enriched = enrichRowData(rowData);
                    syncTableRow(rowKey, enriched);
                    syncHiddenRow(rowKey, enriched);
                    return;
                }

                rowStore[rowKey] = rowData;
            });
        }

        function rowMarkup(rowKey, rowData) {
            const assignmentBadges = renderAssignmentGroups(rowData.assignment_groups || buildAssignmentGroups(rowData));

            return '<tr data-deliverable-row data-row-key="' + escapeHtml(rowKey) + '" data-deliverable-row-data=\'' + JSON.stringify(rowData).replace(/'/g, '&#39;') + '\'>' +
                '<td><div class="fw-semibold">' + escapeHtml(rowData.contact_family_label || '—') + '</div>' +
                '<div class="text-muted small">' + escapeHtml(rowData.activity_type_label || 'Any activity type') + '</div>' +
                (rowData.program_label ? '<div class="text-muted small">Program: ' + escapeHtml(rowData.program_label) + '</div>' : '') + '</td>' +
                '<td><div class="small">' + escapeHtml(rowData.rules_summary || '—') + '</div></td>' +
                '<td class="text-wrap align-top" style="min-width:180px;max-width:280px;white-space:normal;">' + assignmentBadges + '</td>' +
                '<td class="text-wrap" style="min-width:200px;max-width:100%;white-space:normal;">' + (rowData.notes ? escapeHtml(rowData.notes) : '—') + '</td>' +
                '<td class="text-end text-nowrap"><div class="btn-group btn-group-sm" role="group">' +
                '<button type="button" class="btn btn-outline-secondary" data-deliverable-edit data-bs-toggle="tooltip" data-bs-title="Edit deliverable"><i class="bi bi-pencil-square"></i></button>' +
                '<button type="button" class="btn btn-outline-secondary" data-deliverable-duplicate data-bs-toggle="tooltip" data-bs-title="Duplicate deliverable"><i class="bi bi-files"></i></button>' +
                '<button type="button" class="btn btn-outline-danger" data-deliverable-remove data-bs-toggle="tooltip" data-bs-title="Remove deliverable"><i class="bi bi-trash"></i></button>' +
                '</div></td></tr>';
        }

        function hiddenMarkup(rowKey, rowData) {
            const userInputs = (rowData.user_ids || []).map(function (id) {
                return '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][user_ids][]" value="' + escapeHtml(id) + '">';
            }).join('');
            const teamInputs = (rowData.team_ids || []).map(function (id) {
                return '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][team_ids][]" value="' + escapeHtml(id) + '">';
            }).join('');

            return '<div data-deliverable-hidden-row="' + escapeHtml(rowKey) + '">' +
                (rowData.id ? '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][id]" value="' + escapeHtml(rowData.id) + '">' : '') +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][_delete]" value="' + escapeHtml(rowData._delete || '0') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][contact_family_id]" value="' + escapeHtml(rowData.contact_family_id || '') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][activity_type_id]" value="' + escapeHtml(rowData.activity_type_id || '') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][program_id]" value="' + escapeHtml(rowData.program_id || '') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][metric_type]" value="' + escapeHtml(rowData.metric_type || '') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][time_basis]" value="' + escapeHtml(rowData.time_basis || 'observed') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][contribution_basis]" value="' + escapeHtml(rowData.contribution_basis || '') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][user_grouping_mode]" value="' + escapeHtml(rowData.user_grouping_mode || '') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][include_additional_time]" value="' + (rowData.include_additional_time ? '1' : '0') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][target_quantity]" value="' + escapeHtml(rowData.target_quantity || '') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][suggested_due_date]" value="' + escapeHtml(rowData.suggested_due_date || '') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][sort_order]" value="' + escapeHtml(rowData.sort_order || '0') + '">' +
                '<input type="hidden" name="deliverables[' + escapeHtml(rowKey) + '][notes]" value="' + escapeHtml(rowData.notes || '') + '">' +
                userInputs + teamInputs + '</div>';
        }

        function syncHiddenRow(rowKey, rowData) {
            const existing = hiddenInputs.querySelector('[data-deliverable-hidden-row="' + CSS.escape(rowKey) + '"]');
            if (existing) existing.outerHTML = hiddenMarkup(rowKey, rowData);
            else hiddenInputs.insertAdjacentHTML('beforeend', hiddenMarkup(rowKey, rowData));
            rowStore[rowKey] = rowData;
        }

        function syncTableRow(rowKey, rowData) {
            const emptyRow = tableBody.querySelector('.deliverable-empty-row');
            if (emptyRow) emptyRow.remove();
            const existing = tableBody.querySelector('[data-row-key="' + CSS.escape(rowKey) + '"]');
            if (existing) disposeTooltips(existing);
            const markup = rowMarkup(rowKey, rowData);
            if (existing) existing.outerHTML = markup;
            else tableBody.insertAdjacentHTML('beforeend', markup);
            initTooltips(tableBody);
            rowStore[rowKey] = rowData;
        }

        function deleteRow(rowKey) {
            const row = tableBody.querySelector('[data-row-key="' + CSS.escape(rowKey) + '"]');
            const hidden = hiddenInputs.querySelector('[data-deliverable-hidden-row="' + CSS.escape(rowKey) + '"]');
            if (row) disposeTooltips(row);
            if (row) row.remove();
            if (hidden) hidden.remove();
            delete rowStore[rowKey];
            renderEmptyStateIfNeeded();
        }

        function markRowDeleted(rowKey) {
            const hidden = hiddenInputs.querySelector('[data-deliverable-hidden-row="' + CSS.escape(rowKey) + '"]');
            if (!hidden) return;
            const deleteInput = hidden.querySelector('input[name$="[_delete]"]');
            if (deleteInput) deleteInput.value = '1';
            const row = tableBody.querySelector('[data-row-key="' + CSS.escape(rowKey) + '"]');
            if (row) {
                disposeTooltips(row);
                row.classList.add('table-active', 'text-muted');
                row.style.display = 'none';
            }
            if (rowStore[rowKey]) rowStore[rowKey]._delete = '1';
            renderEmptyStateIfNeeded();
        }

        function bindRows() {
            tableBody.querySelectorAll('[data-deliverable-row]').forEach(function (row) {
                const rowKey = row.dataset.rowKey;
                if (rowKey && !rowStore[rowKey] && row.dataset.deliverableRowData) {
                    try { rowStore[rowKey] = JSON.parse(row.dataset.deliverableRowData); } catch (e) { rowStore[rowKey] = {}; }
                }
            });
            initTooltips(tableBody);
        }

        function rowHasContent(rowData) {
            if (!rowData.contact_family_id || !rowData.metric_type || !rowData.contribution_basis) {
                return false;
            }

            if (rowData.target_quantity === '' || rowData.target_quantity === null || rowData.target_quantity === undefined) {
                return false;
            }

            if (rowData.contribution_basis === 'user' && !rowData.user_grouping_mode) {
                return false;
            }

            return true;
        }

        tableBody.addEventListener('click', function (event) {
            const actionButton = event.target.closest('[data-deliverable-edit], [data-deliverable-duplicate], [data-deliverable-remove]');
            if (!actionButton) return;
            const row = actionButton.closest('[data-deliverable-row]');
            if (!row) return;
            const rowKey = row.dataset.rowKey;
            const payload = rowStore[rowKey] || {};

            if (actionButton.matches('[data-deliverable-edit]')) {
                setEditorData(rowKey, payload);
                return;
            }
            if (actionButton.matches('[data-deliverable-duplicate]')) {
                const duplicateKey = newRowKey();
                const duplicate = Object.assign({}, payload, { id: '', _delete: '0', classification_locked: false, semantic_locked: false });
                delete duplicate.row_key;
                syncTableRow(duplicateKey, duplicate);
                syncHiddenRow(duplicateKey, duplicate);
                return;
            }
            if (actionButton.matches('[data-deliverable-remove]')) {
                if (payload.id) markRowDeleted(rowKey);
                else deleteRow(rowKey);
                if (currentKey === rowKey) clearEditor(false);
            }
        });

        addButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const newKey = newRowKey();
                currentKey = newKey;
                clearEditor();
                editorKeyInput.value = newKey;
            });
        });

        clearButton.addEventListener('click', function () { clearEditor(); });

        saveButton.addEventListener('click', function () {
            const rowData = collectEditorData();
            const rowKey = currentKey || newRowKey();
            if (!rowHasContent(rowData)) return;
            syncTableRow(rowKey, rowData);
            syncHiddenRow(rowKey, rowData);
            editorModal?.hide();
        });

        editorFieldset.querySelector('[data-deliverable-contact-family]')?.addEventListener('change', function () {
            syncActivityTypeOptions();
            syncEditorVisibility();
        });
        editorFieldset.querySelector('[data-deliverable-activity-type]')?.addEventListener('change', syncEditorVisibility);
        editorFieldset.querySelectorAll('[data-deliverable-metric], [data-deliverable-time-basis], [data-deliverable-basis], [data-deliverable-grouping]').forEach(function (input) {
            input.addEventListener('change', syncEditorVisibility);
        });

        editorFieldset.querySelector('[data-deliverable-select-all]')?.addEventListener('click', function () {
            editorFieldset.querySelectorAll('[data-deliverable-team-checkbox]').forEach(function (cb) {
                cb.checked = true;
                cb.dispatchEvent(new Event('change'));
            });
            editorFieldset.querySelectorAll('[data-deliverable-user-checkbox]').forEach(function (cb) {
                cb.checked = true;
            });
        });

        document.addEventListener('agreement-scope:change', function () {
            syncActivityTypeOptions();
            syncStoredRowsToScope();
            if (editorModalEl.classList.contains('show')) {
                const assignment = getSelectedAssignmentState();
                renderAssignmentLedger(assignment.user_ids, assignment.team_ids);
            }
        });

        const teamPicker = document.getElementById(agreementTeamPickerId);
        const userPicker = document.getElementById(agreementUserPickerId);
        [teamPicker, userPicker].forEach(function (picker) {
            if (!picker) return;
            picker.addEventListener('token-picker:change', function () {
                syncStoredRowsToScope();
                if (editorModalEl.classList.contains('show')) {
                    const assignment = getSelectedAssignmentState();
                    renderAssignmentLedger(assignment.user_ids, assignment.team_ids);
                }
            });
        });

        bindRows();
        syncActivityTypeOptions();
        syncStoredRowsToScope();
    });
})();
</script>
@endonce

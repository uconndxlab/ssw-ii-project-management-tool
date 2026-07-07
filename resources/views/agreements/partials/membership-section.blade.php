@php
    $agreement = $agreement ?? null;
    $selectedUserIds = old('user_ids', $agreement?->users?->pluck('id')->toArray() ?? []);
    $selectedTeamIds = old('team_ids', $agreement?->teams?->pluck('id')->toArray() ?? []);
    $selectedPrincipalInvestigatorIds = old('principal_investigator_ids', $agreement?->principalInvestigators?->pluck('id')->toArray() ?? []);

    $selectedTeamUserIds = collect($selectedTeamIds)
        ->flatMap(function ($teamId) use ($teams) {
            return $teams->firstWhere('id', (int) $teamId)?->users?->pluck('id') ?? [];
        })
        ->map(fn ($id) => (string) $id)
        ->unique()
        ->values()
        ->toArray();

    $selectedAdditionalUserIds = array_values(array_diff(
        array_map('strval', $selectedUserIds),
        $selectedTeamUserIds
    ));

    $selectedPrincipalInvestigatorIds = array_values(array_map('strval', $selectedPrincipalInvestigatorIds));

    $agreementUserOptions = $users->map(function ($user) {
        $role = !empty($user->role) ? ' (' . ucfirst($user->role) . ')' : '';

        return [
            'value' => $user->id,
            'label' => $user->name . $role,
            'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')),
        ];
    });
@endphp

<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3">Teams & Users</h5>

        <div class="row g-5 align-items-stretch">
            <div class="col-lg-5 d-flex">
                <div class="d-flex flex-column w-100 h-100">
                    <div class="d-grid gap-4">
                        <div>
                            <label class="form-label">Teams</label>

                            <x-token-picker
                                picker-id="agreement-{{ $agreement ? 'edit' : 'create' }}-teams"
                                name="team_ids[]"
                                :options="$teams->map(function ($team) {
                                    return [
                                        'value' => $team->id,
                                        'label' => $team->name,
                                        'search' => $team->name,
                                    ];
                                })"
                                :selected-ids="$selectedTeamIds"
                                label-key="label"
                                value-key="value"
                                search-key="search"
                                placeholder="Search to assign teams..."
                                :open-on-focus="false"
                                :show-selected="false"
                                :height="'220px'"
                            />

                            <small class="text-muted d-block mt-2">
                                Team members inherit access to this agreement.
                            </small>
                        </div>

                        <div>
                            <label class="form-label">Additional users</label>

                            <x-token-picker
                                picker-id="agreement-{{ $agreement ? 'edit' : 'create' }}-users"
                                name="user_ids[]"
                                :options="$agreementUserOptions"
                                :selected-ids="$selectedAdditionalUserIds"
                                label-key="label"
                                value-key="value"
                                search-key="search"
                                placeholder="Search additional users..."
                                :open-on-focus="false"
                                :show-selected="false"
                                :height="'220px'"
                            />

                            <small class="text-muted d-block mt-2">
                                Use this for users who are not covered by a selected team.
                            </small>
                        </div>
                    </div>

                    <div class="border rounded bg-body-tertiary px-3 py-3 mt-3 mt-lg-auto">
                        <div class="fw-semibold small mb-2">Membership requirements</div>

                        <div class="d-grid gap-2 small">
                            <div class="d-flex align-items-start gap-2" data-membership-requirement-members>
                                <i class="bi bi-x-circle-fill text-danger" data-membership-requirement-icon aria-hidden="true"></i>
                                <div>
                                    <div>Add at least one user or team to the agreement.</div>
                                </div>
                            </div>

                            <div class="d-flex align-items-start gap-2" data-membership-requirement-principal-investigators>
                                <i class="bi bi-x-circle-fill text-danger" data-membership-requirement-icon aria-hidden="true"></i>
                                <div>
                                    <div>Select at least one principal investigator.</div>
                                    <div class="text-muted">Use the PI toggle on a selected user in the roster.</div>
                                </div>
                            </div>
                        </div>

                        @error('team_ids')
                            <div class="text-danger small mt-3">{{ $message }}</div>
                        @enderror

                        @error('principal_investigator_ids')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="border rounded overflow-hidden d-flex flex-column h-100" style="min-height: 420px; background-color: #e9ecef;">
                    <div class="small text-muted px-3 py-2 border-bottom bg-body">
                        Selected users
                    </div>

                    <div class="flex-grow-1 overflow-auto" style="min-height: 0;">
                        <div class="m-3 mt-2 mb-2" data-agreement-membership-section
                             data-team-picker-id="agreement-{{ $agreement ? 'edit' : 'create' }}-teams"
                             data-user-picker-id="agreement-{{ $agreement ? 'edit' : 'create' }}-users"
                             data-all-user-ids='@json($agreementUserOptions->pluck("value")->values())'
                             data-selected-principal-investigator-ids='@json($selectedPrincipalInvestigatorIds)'
                             data-user-labels='@json($agreementUserOptions->pluck("label", "value"))'
                             data-team-labels='@json($teams->pluck("name", "id"))'
                             data-team-members='@json($teams->mapWithKeys(function ($team) {
                                 return [(string) $team->id => $team->users->pluck("id")->map(fn ($id) => (string) $id)->values()];
                             }))'>
                            <div data-principal-investigator-inputs></div>
                            <div class="d-grid gap-2" data-membership-ledger></div>

                            <div class="text-muted small py-3 d-none" data-membership-summary-empty>
                                No teams or additional users have been added yet.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@once
<script>
(function () {
    function parseJson(text, fallback) {
        try {
            return JSON.parse(text || '');
        } catch (error) {
            return fallback;
        }
    }

    function getSelectedIdsFromTeamPicker(teamPicker) {
        return Array.from(teamPicker.querySelectorAll('[data-token-inputs] input[type="hidden"]')).map(function (input) {
            return String(input.value);
        });
    }

    function getSelectedIdsFromTokenPicker(tokenPicker) {
        return Array.from(tokenPicker.querySelectorAll('[data-token-inputs] input[type="hidden"]')).map(function (input) {
            return String(input.value);
        });
    }

    function setSelectedIdsOnTokenPicker(tokenPicker, values) {
        tokenPicker.dispatchEvent(new CustomEvent('token-picker:set', {
            detail: values,
            bubbles: true,
        }));
    }

    function getSelectedPrincipalInvestigatorIds(section) {
        return Array.from(section.querySelectorAll('[data-principal-investigator-inputs] input[type="hidden"]')).map(function (input) {
            return String(input.value);
        });
    }

    function setSelectedPrincipalInvestigatorIds(section, values) {
        const container = section.querySelector('[data-principal-investigator-inputs]');

        if (!container) {
            return;
        }

        const uniqueValues = Array.from(new Set((values || []).map(function (value) {
            return String(value);
        })));

        container.innerHTML = '';

        uniqueValues.forEach(function (value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'principal_investigator_ids[]';
            input.value = value;
            container.appendChild(input);
        });
    }

    function initializeMembershipTooltips(scope) {
        if (!window.bootstrap || !bootstrap.Tooltip) {
            return;
        }

        scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
            bootstrap.Tooltip.getOrCreateInstance(element);
        });
    }

    function disposeMembershipTooltips(scope) {
        if (!window.bootstrap || !bootstrap.Tooltip || !scope) {
            return;
        }

        scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
            const tooltip = bootstrap.Tooltip.getInstance(element);

            if (tooltip) {
                tooltip.dispose();
            }
        });
    }

    function getMembershipPickers(section) {
        const scope = section.closest('.card-body') || document;

        return {
            teamPicker: scope.querySelector('#' + section.dataset.teamPickerId),
            tokenPicker: scope.querySelector('#' + section.dataset.userPickerId),
        };
    }

    function getEffectiveUserIds(section) {
        const pickers = getMembershipPickers(section);
        const teamPicker = pickers.teamPicker;
        const tokenPicker = pickers.tokenPicker;

        if (!teamPicker || !tokenPicker) {
            return [];
        }

        const teamMembers = parseJson(section.dataset.teamMembers, {});
        const selectedTeamIds = getSelectedIdsFromTeamPicker(teamPicker);
        const selectedUserIds = getSelectedIdsFromTokenPicker(tokenPicker);
        const effectiveUserIds = new Set(selectedUserIds.map(function (userId) {
            return String(userId);
        }));

        selectedTeamIds.forEach(function (teamId) {
            const memberIds = Array.isArray(teamMembers[teamId]) ? teamMembers[teamId] : [];
            memberIds.forEach(function (memberId) {
                effectiveUserIds.add(String(memberId));
            });
        });

        return Array.from(effectiveUserIds);
    }

    function syncPrincipalInvestigatorSelections(section) {
        const effectiveUserIds = new Set(getEffectiveUserIds(section));
        const nextPrincipalInvestigatorIds = getSelectedPrincipalInvestigatorIds(section).filter(function (userId) {
            return effectiveUserIds.has(String(userId));
        });

        setSelectedPrincipalInvestigatorIds(section, nextPrincipalInvestigatorIds);
    }

    function updateRequirementStatus(element, isComplete) {
        if (!element) {
            return;
        }

        const icon = element.querySelector('[data-membership-requirement-icon]');

        if (!icon) {
            return;
        }

        icon.className = isComplete
            ? 'bi bi-check-circle-fill text-success'
            : 'bi bi-x-circle-fill text-danger';
    }

    function renderRequirementNotes(section) {
        const selectedPrincipalInvestigatorIds = getSelectedPrincipalInvestigatorIds(section);
        const effectiveUserIds = getEffectiveUserIds(section);
        const scope = section.closest('.row') || section.closest('.card-body') || document;

        updateRequirementStatus(
            scope.querySelector('[data-membership-requirement-members]'),
            effectiveUserIds.length > 0
        );

        updateRequirementStatus(
            scope.querySelector('[data-membership-requirement-principal-investigators]'),
            selectedPrincipalInvestigatorIds.length > 0
        );
    }

    function renderMembershipSummary(section) {
        const pickers = getMembershipPickers(section);
        const teamPicker = pickers.teamPicker;
        const tokenPicker = pickers.tokenPicker;
        const body = section.querySelector('[data-membership-ledger]');
        const emptyState = section.querySelector('[data-membership-summary-empty]');

        if (!teamPicker || !tokenPicker || !body || !emptyState) {
            return;
        }

        const teamLabels = parseJson(section.dataset.teamLabels, {});
        const userLabels = parseJson(section.dataset.userLabels, {});
        const teamMembers = parseJson(section.dataset.teamMembers, {});
        const selectedPrincipalInvestigatorIds = new Set(getSelectedPrincipalInvestigatorIds(section));

        const selectedTeamIds = getSelectedIdsFromTeamPicker(teamPicker);
        const selectedUserIds = getSelectedIdsFromTokenPicker(tokenPicker);
        const selectedTeamMemberIds = new Set();

        disposeMembershipTooltips(body);

        selectedTeamIds.forEach(function (teamId) {
            const memberIds = Array.isArray(teamMembers[teamId]) ? teamMembers[teamId] : [];
            memberIds.forEach(function (memberId) {
                selectedTeamMemberIds.add(String(memberId));
            });
        });

        const directUserIds = selectedUserIds.filter(function (userId) {
            return !selectedTeamMemberIds.has(String(userId));
        });

        body.innerHTML = '';

        function createRemoveButton(label, onClick) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-link text-danger text-decoration-none p-0 lh-1 fs-4';
            button.innerHTML = '&times;';
            button.setAttribute('aria-label', label);
            button.setAttribute('data-bs-toggle', 'tooltip');
            button.setAttribute('data-bs-title', label);
            button.addEventListener('click', function () {
                disposeMembershipTooltips(body);
                onClick();
            });

            return button;
        }

        function createPrincipalInvestigatorToggle(userId) {
            const wrapper = document.createElement('label');
            wrapper.className = 'form-check form-switch m-0 d-inline-flex align-items-center gap-2';

            const input = document.createElement('input');
            input.className = 'form-check-input mt-0';
            input.type = 'checkbox';
            input.checked = selectedPrincipalInvestigatorIds.has(String(userId));
            input.setAttribute('aria-label', 'Mark as principal investigator');
            input.setAttribute('data-bs-toggle', 'tooltip');
            input.setAttribute('data-bs-title', 'Principal investigator');
            input.addEventListener('change', function () {
                const nextPrincipalInvestigatorIds = new Set(getSelectedPrincipalInvestigatorIds(section));

                if (input.checked) {
                    nextPrincipalInvestigatorIds.add(String(userId));
                } else {
                    nextPrincipalInvestigatorIds.delete(String(userId));
                }

                setSelectedPrincipalInvestigatorIds(section, Array.from(nextPrincipalInvestigatorIds));
                renderRequirementNotes(section);
            });

            const text = document.createElement('span');
            text.className = 'small text-muted';
            text.textContent = 'PI';

            wrapper.appendChild(input);
            wrapper.appendChild(text);

            return wrapper;
        }

        function appendInheritedMemberRow(container, userId) {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center gap-2 py-1 ps-3 pe-2 border-top';

            const textWrap = document.createElement('div');
            textWrap.className = 'small text-muted';
            textWrap.textContent = userLabels[String(userId)] || ('User ' + userId);

            const actions = document.createElement('div');
            actions.className = 'd-flex align-items-center gap-3';
            actions.appendChild(createPrincipalInvestigatorToggle(userId));

            row.appendChild(textWrap);
            row.appendChild(actions);
            container.appendChild(row);
        }

        selectedTeamIds.forEach(function (teamId) {
            const memberIds = Array.isArray(teamMembers[teamId]) ? teamMembers[teamId] : [];

            const card = document.createElement('div');
            card.className = 'border rounded overflow-hidden bg-body';

            const header = document.createElement('div');
            header.className = 'd-flex justify-content-between align-items-start gap-2 px-3 py-2 bg-light';

            const titleWrap = document.createElement('div');
            const title = document.createElement('div');
            title.className = 'fw-semibold small';
            title.textContent = teamLabels[String(teamId)] || ('Team ' + teamId);

            const meta = document.createElement('div');
            meta.className = 'small text-muted';
            meta.textContent = memberIds.length + ' member' + (memberIds.length === 1 ? '' : 's');

            titleWrap.appendChild(title);
            titleWrap.appendChild(meta);

            const removeButton = createRemoveButton('Remove team', function () {
                const nextTeamIds = getSelectedIdsFromTeamPicker(teamPicker).filter(function (value) {
                    return value !== String(teamId);
                });

                setSelectedIdsOnTokenPicker(teamPicker, nextTeamIds);
            });

            header.appendChild(titleWrap);
            header.appendChild(removeButton);
            card.appendChild(header);

            memberIds.forEach(function (memberId) {
                appendInheritedMemberRow(card, memberId);
            });

            body.appendChild(card);
        });

        if (directUserIds.length > 0) {
            const card = document.createElement('div');
            card.className = 'border rounded overflow-hidden bg-body';

            const header = document.createElement('div');
            header.className = 'px-3 py-2 bg-light';

            const title = document.createElement('div');
            title.className = 'fw-semibold small';
            title.textContent = 'Additional users';

            const meta = document.createElement('div');
            meta.className = 'small text-muted';
            meta.textContent = directUserIds.length + ' user' + (directUserIds.length === 1 ? '' : 's');

            header.appendChild(title);
            header.appendChild(meta);
            card.appendChild(header);

            directUserIds.forEach(function (userId) {
                const row = document.createElement('div');
                row.className = 'd-flex justify-content-between align-items-center gap-2 py-1 px-3 border-top';

                const textWrap = document.createElement('div');
                textWrap.className = 'small';
                textWrap.textContent = userLabels[String(userId)] || ('User ' + userId);

                const actions = document.createElement('div');
                actions.className = 'd-flex align-items-center gap-3';
                actions.appendChild(createPrincipalInvestigatorToggle(userId));

                const removeButton = createRemoveButton('Remove additional user', function () {
                    const nextUserIds = getSelectedIdsFromTokenPicker(tokenPicker).filter(function (value) {
                        return value !== String(userId);
                    });

                    setSelectedIdsOnTokenPicker(tokenPicker, nextUserIds);
                });

                actions.appendChild(removeButton);
                row.appendChild(textWrap);
                row.appendChild(actions);
                card.appendChild(row);
            });

            body.appendChild(card);
        }

        const hasRows = body.children.length > 0;
        emptyState.classList.toggle('d-none', hasRows);
        initializeMembershipTooltips(body);
    }

    function syncAdditionalUserRestrictions(section) {
        const pickers = getMembershipPickers(section);
        const teamPicker = pickers.teamPicker;
        const tokenPicker = pickers.tokenPicker;

        if (!teamPicker || !tokenPicker) {
            return;
        }

        const allUserIds = parseJson(section.dataset.allUserIds, []);
        const teamMembers = parseJson(section.dataset.teamMembers, {});
        const selectedTeamIds = getSelectedIdsFromTeamPicker(teamPicker);
        const restrictedIds = new Set();

        selectedTeamIds.forEach(function (teamId) {
            const memberIds = Array.isArray(teamMembers[teamId]) ? teamMembers[teamId] : [];
            memberIds.forEach(function (memberId) {
                restrictedIds.add(String(memberId));
            });
        });

        const allowedIds = allUserIds.map(function (id) {
            return String(id);
        }).filter(function (userId) {
            return !restrictedIds.has(String(userId));
        });

        tokenPicker.dispatchEvent(new CustomEvent('token-picker:restrict', {
            detail: allowedIds,
            bubbles: true,
        }));
    }

    function initializeMembershipSection(section) {
        if (section.dataset.membershipSectionInitialized === 'true') {
            return;
        }

        const pickers = getMembershipPickers(section);
        const teamPicker = pickers.teamPicker;
        const tokenPicker = pickers.tokenPicker;

        if (!teamPicker || !tokenPicker) {
            return;
        }

        const refresh = function () {
            syncAdditionalUserRestrictions(section);
            syncPrincipalInvestigatorSelections(section);
            renderMembershipSummary(section);
            renderRequirementNotes(section);
        };

        setSelectedPrincipalInvestigatorIds(section, parseJson(section.dataset.selectedPrincipalInvestigatorIds, []));

        teamPicker.addEventListener('token-picker:change', refresh);
        tokenPicker.addEventListener('token-picker:change', function () {
            refresh();
        });

        refresh();
        section.dataset.membershipSectionInitialized = 'true';
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-agreement-membership-section]').forEach(function (section) {
            initializeMembershipSection(section);
        });
    });
})();
</script>
@endonce

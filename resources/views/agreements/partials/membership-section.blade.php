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
        return [
            'value' => $user->id,
            'label' => $user->name,
            'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')),
            'meta' => filled($user->po_number) ? 'PO: ' . $user->po_number : null,
            'contextLabels' => ['User'],
        ];
    });

    $membershipEntityBadgeClasses = [
        'team' => \App\Support\EntityBadge::relationClasses('team'),
        'role' => \App\Support\EntityBadge::categoryClasses('role'),
    ];
@endphp

<x-section-card title="Teams & Users">

        <div class="row g-5 align-items-stretch">
            <div class="col-lg-5 d-flex">
                <div class="d-flex flex-column w-100 h-100">
                    <div class="d-grid gap-4">
                        <x-form-field label="Teams" name="team_ids" help="Team members inherit access to this agreement." class="mb-0">
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
                                disabled-placeholder="Select at least one program first..."
                                :disabled="empty($selectedProgramIds)"
                                :open-on-focus="false"
                                :show-selected="false"
                                :height="'220px'"
                                entity="team"
                            />
                        </x-form-field>

                        <x-form-field label="Additional users" name="user_ids" help="Users who are not covered by a selected team." class="mb-0">
                            <x-token-picker
                                picker-id="agreement-{{ $agreement ? 'edit' : 'create' }}-users"
                                name="user_ids[]"
                                :options="$agreementUserOptions"
                                :selected-ids="$selectedAdditionalUserIds"
                                label-key="label"
                                value-key="value"
                                search-key="search"
                                placeholder="Search additional users..."
                                disabled-placeholder="Select at least one program first..."
                                :disabled="empty($selectedProgramIds)"
                                :open-on-focus="false"
                                :show-selected="false"
                                :height="'220px'"
                                entity="user"
                            />
                        </x-form-field>
                    </div>

                    <div class="border rounded px-3 py-3 mt-3 mt-lg-auto{{ ($errors->has('membership') || $errors->has('principal_investigator_ids')) ? ' border-danger' : ' bg-body-tertiary' }}">
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

                        @error('membership')
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
                             data-program-allowed-user-ids='@json($agreementUserOptions->pluck("value")->values())'
                             data-selected-principal-investigator-ids='@json($selectedPrincipalInvestigatorIds)'
                             data-user-names='@json($users->mapWithKeys(fn ($user) => [(string) $user->id => $user->name]))'
                             data-user-po-numbers='@json($users->mapWithKeys(fn ($user) => [(string) $user->id => $user->po_number]))'
                             data-user-roles='@json($users->mapWithKeys(fn ($user) => [(string) $user->id => $user->role ?? '']))'
                             data-entity-badge-classes='@json($membershipEntityBadgeClasses)'
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
</x-section-card>

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
        const userNames = parseJson(section.dataset.userNames, {});
        const userPoNumbers = parseJson(section.dataset.userPoNumbers, {});
        const userRoles = parseJson(section.dataset.userRoles, {});
        const entityBadgeClasses = parseJson(section.dataset.entityBadgeClasses, {});
        const teamMembers = parseJson(section.dataset.teamMembers, {});
        const selectedPrincipalInvestigatorIds = new Set(getSelectedPrincipalInvestigatorIds(section));

        const selectedTeamIds = getSelectedIdsFromTeamPicker(teamPicker);
        const selectedUserIds = getSelectedIdsFromTokenPicker(tokenPicker);
        const selectedTeamMemberIds = new Set();

        disposeTooltips(body);

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

        function createTeamBadge(teamName) {
            const badge = document.createElement('span');
            badge.className = 'badge ' + (entityBadgeClasses.team || 'bg-secondary-subtle text-secondary-emphasis border');
            badge.textContent = teamName;

            return badge;
        }

        function createMemberLabel(userId) {
            const wrap = document.createElement('div');
            wrap.className = 'd-grid gap-1 min-w-0';

            const primary = document.createElement('div');
            primary.className = 'd-inline-flex flex-wrap align-items-center gap-1 small';

            const name = document.createElement('span');
            name.className = 'fw-semibold text-dark';
            name.textContent = userNames[String(userId)] || ('User ' + userId);
            primary.appendChild(name);

            const role = userRoles[String(userId)];
            if (role) {
                const roleBadge = document.createElement('span');
                roleBadge.className = 'badge ' + (entityBadgeClasses.role || 'bg-light text-muted border');
                roleBadge.textContent = role.charAt(0).toUpperCase() + role.slice(1);
                primary.appendChild(roleBadge);
            }

            wrap.appendChild(primary);

            const poNumber = userPoNumbers[String(userId)];
            if (poNumber) {
                const meta = document.createElement('div');
                meta.className = 'small text-muted';
                meta.textContent = 'PO: ' + poNumber;
                wrap.appendChild(meta);
            }

            return wrap;
        }

        function createRemoveButton(label, onClick) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-sm btn-link text-danger text-decoration-none p-0 lh-1 fs-4';
            button.innerHTML = '&times;';
            button.setAttribute('aria-label', label);
            button.setAttribute('data-bs-toggle', 'tooltip');
            button.setAttribute('data-bs-title', label);
            button.addEventListener('click', function () {
                disposeTooltips(body);
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

            const actions = document.createElement('div');
            actions.className = 'd-flex align-items-center gap-3';
            actions.appendChild(createPrincipalInvestigatorToggle(userId));

            row.appendChild(createMemberLabel(userId));
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
            titleWrap.className = 'd-flex flex-wrap align-items-center gap-2';
            titleWrap.appendChild(createTeamBadge(teamLabels[String(teamId)] || ('Team ' + teamId)));

            const meta = document.createElement('span');
            meta.className = 'badge bg-light text-muted border rounded-pill';
            meta.textContent = memberIds.length + ' member' + (memberIds.length === 1 ? '' : 's');
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
                row.appendChild(createMemberLabel(userId));
                row.appendChild(actions);
                card.appendChild(row);
            });

            body.appendChild(card);
        }

        const hasRows = body.children.length > 0;
        emptyState.classList.toggle('d-none', hasRows);
        initTooltips(body);
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
        const programAllowedUserIds = new Set(parseJson(section.dataset.programAllowedUserIds, allUserIds).map(function (id) {
            return String(id);
        }));
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
            return programAllowedUserIds.has(String(userId)) && !restrictedIds.has(String(userId));
        });

        const nextRestrictionKey = JSON.stringify(allowedIds);

        if (section.dataset.additionalUserRestrictionKey === nextRestrictionKey) {
            return;
        }

        section.dataset.additionalUserRestrictionKey = nextRestrictionKey;

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
        section.addEventListener('agreement-scope:change', refresh);

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

@php
    $agreement = $agreement ?? null;
    $selectedUserIds = old('user_ids', $agreement?->users?->pluck('id')->toArray() ?? []);
    $selectedTeamIds = old('team_ids', $agreement?->teams?->pluck('id')->toArray() ?? []);

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

        <div class="row g-5 align-items-start">
            <div class="col-lg-5">
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
            </div>

            <div class="col-lg-7">
                <div class="border rounded overflow-hidden d-flex flex-column" style="height: 420px; background-color: #e9ecef;">
                    <div class="small text-muted px-3 py-2 border-bottom bg-body">
                        Selected users
                    </div>

                    <div class="flex-grow-1 overflow-auto" style="min-height: 0;">
                        <div class="m-3 mt-2 mb-2" data-agreement-membership-section
                             data-team-picker-id="agreement-{{ $agreement ? 'edit' : 'create' }}-teams"
                             data-user-picker-id="agreement-{{ $agreement ? 'edit' : 'create' }}-users"
                             data-all-user-ids='@json($agreementUserOptions->pluck("value")->values())'
                             data-user-labels='@json($agreementUserOptions->pluck("label", "value"))'
                             data-team-labels='@json($teams->pluck("name", "id"))'
                             data-team-members='@json($teams->mapWithKeys(function ($team) {
                                 return [(string) $team->id => $team->users->pluck("id")->map(fn ($id) => (string) $id)->values()];
                             }))'>
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

    function initializeMembershipTooltips(scope) {
        if (!window.bootstrap || !bootstrap.Tooltip) {
            return;
        }

        scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
            bootstrap.Tooltip.getOrCreateInstance(element);
        });
    }

    function getMembershipPickers(section) {
        const scope = section.closest('.card-body') || document;

        return {
            teamPicker: scope.querySelector('#' + section.dataset.teamPickerId),
            tokenPicker: scope.querySelector('#' + section.dataset.userPickerId),
        };
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

        const selectedTeamIds = getSelectedIdsFromTeamPicker(teamPicker);
        const selectedUserIds = getSelectedIdsFromTokenPicker(tokenPicker);
        const selectedTeamMemberIds = new Set();

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
            button.addEventListener('click', onClick);

            return button;
        }

        function appendInheritedMemberRow(container, userId) {
            const row = document.createElement('div');
            row.className = 'd-flex justify-content-between align-items-center gap-2 py-1 ps-3 pe-2 border-top';

            const textWrap = document.createElement('div');
            textWrap.className = 'small text-muted';
            textWrap.textContent = userLabels[String(userId)] || ('User ' + userId);

            row.appendChild(textWrap);
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

                const removeButton = createRemoveButton('Remove additional user', function () {
                    const nextUserIds = getSelectedIdsFromTokenPicker(tokenPicker).filter(function (value) {
                        return value !== String(userId);
                    });

                    setSelectedIdsOnTokenPicker(tokenPicker, nextUserIds);
                });

                row.appendChild(textWrap);
                row.appendChild(removeButton);
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
            renderMembershipSummary(section);
        };

        teamPicker.addEventListener('token-picker:change', refresh);
        tokenPicker.addEventListener('token-picker:change', function () {
            renderMembershipSummary(section);
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
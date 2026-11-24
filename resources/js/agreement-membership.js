import { disposeTooltips, initTooltips } from './tooltips.js';

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
        meta.className = 'badge app-tag';
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

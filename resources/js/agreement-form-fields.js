function selectedIdsFromPicker(picker) {
    if (!picker) {
        return [];
    }

    return Array.from(picker.querySelectorAll('[data-token-inputs] input')).map(function (input) {
        return String(input.value);
    });
}

function normalizeProgramMap(map) {
    return Object.entries(map || {}).reduce(function (carry, entry) {
        carry[String(entry[0])] = Array.isArray(entry[1]) ? entry[1].map(String) : [];
        return carry;
    }, {});
}

function normalizeModeMap(map) {
    return Object.entries(map || {}).reduce(function (carry, entry) {
        carry[String(entry[0])] = String(entry[1] || 'specific');
        return carry;
    }, {});
}

function matchesProgramScope(programIds, scopeMode, selectedProgramIds, selectedScopeMode, allowGlobal) {
    const normalizedProgramIds = Array.isArray(programIds) ? programIds.map(String) : [];
    const selectedPrograms = new Set((selectedProgramIds || []).map(String));
    const normalizedScopeMode = String(scopeMode || 'specific');
    const normalizedSelectedScopeMode = String(selectedScopeMode || 'specific');

    if (normalizedSelectedScopeMode === 'all') {
        return normalizedScopeMode !== 'none';
    }

    if (normalizedScopeMode === 'all') {
        return true;
    }

    if (normalizedScopeMode === 'none') {
        return false;
    }

    if (normalizedProgramIds.length === 0) {
        return allowGlobal;
    }

    if (selectedPrograms.size === 0) {
        return false;
    }

    return normalizedProgramIds.some(function (programId) {
        return selectedPrograms.has(String(programId));
    });
}

function allowedIds(programMap, modeMap, selectedProgramIds, selectedScopeMode, allowGlobal) {
    return Object.entries(programMap || {}).reduce(function (carry, entry) {
        const optionId = String(entry[0]);
        const programIds = Array.isArray(entry[1]) ? entry[1].map(String) : [];
        const scopeMode = modeMap && modeMap[optionId] ? String(modeMap[optionId]) : 'specific';

        if (matchesProgramScope(programIds, scopeMode, selectedProgramIds, selectedScopeMode, allowGlobal)) {
            carry.push(optionId);
        }

        return carry;
    }, []);
}

function allowedOrganizationIds(organizationProgramMap, organizationProgramScopeModeMap, organizationStateMap, selectedProgramIds, selectedStateIds, selectedScopeMode) {
    const programAllowed = new Set(allowedIds(organizationProgramMap, organizationProgramScopeModeMap, selectedProgramIds, selectedScopeMode, false));
    const stateAllowed = new Set(Object.entries(organizationStateMap || {}).reduce(function (carry, entry) {
        const optionId = String(entry[0]);
        const stateIds = Array.isArray(entry[1]) ? entry[1].map(String) : [];
        const selectedStates = new Set((selectedStateIds || []).map(String));

        if (stateIds.some(function (stateId) {
            return selectedStates.has(String(stateId));
        })) {
            carry.push(optionId);
        }

        return carry;
    }, []));

    return Array.from(programAllowed).filter(function (organizationId) {
        return stateAllowed.has(String(organizationId));
    });
}

function setPickerRestriction(picker, allowedIds, shouldDisable, disabledPlaceholder) {
    if (!picker) {
        return;
    }

    picker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
        detail: {
            disabled: shouldDisable,
            placeholder: disabledPlaceholder,
        },
        bubbles: true,
    }));

    picker.dispatchEvent(new CustomEvent('token-picker:restrict', {
        detail: allowedIds,
        bubbles: true,
    }));
}

document.addEventListener('DOMContentLoaded', function () {
    const programPicker = document.getElementById('agreement-scope-programs');
    const statePicker = document.getElementById('agreement-states');
    const organizationPicker = document.getElementById('agreement-organizations');
    const membershipSection = document.querySelector('[data-agreement-membership-section]');
    const teamPicker = document.getElementById(membershipSection?.dataset.teamPickerId);
    const userPicker = document.getElementById(membershipSection?.dataset.userPickerId);
    const organizationsSection = document.querySelector('[data-agreement-organizations-section]');

    if (!programPicker) {
        return;
    }

    const root = programPicker.closest('[data-agreement-scope]');
    const config = JSON.parse(root.querySelector('[data-agreement-scope-config]').textContent);
    const allProgramIds = config.allActiveProgramIds;
    const organizationProgramMap = normalizeProgramMap(config.organizationProgramMap);
    const organizationProgramScopeModeMap = normalizeModeMap(config.organizationProgramScopeModeMap);
    const organizationStateMap = normalizeProgramMap(config.organizationStateMap);
    const teamProgramMap = normalizeProgramMap(config.teamProgramMap);
    const teamProgramScopeModeMap = normalizeModeMap(config.teamProgramScopeModeMap);
    const userProgramMap = normalizeProgramMap(config.userProgramMap);
    const userProgramScopeModeMap = normalizeModeMap(config.userProgramScopeModeMap);

    function currentSelectedScopeMode() {
        const checked = document.querySelector('input[name="program_scope_mode"]:checked');

        return checked ? String(checked.value) : 'specific';
    }

    function refreshAgreementScope() {
        const selectedScopeMode = currentSelectedScopeMode();
        const selectedProgramIds = selectedScopeMode === 'all'
            ? allProgramIds.slice()
            : selectedIdsFromPicker(programPicker);
        const selectedStateIds = selectedIdsFromPicker(statePicker);
        const hasSelectedPrograms = selectedProgramIds.length > 0;
        const hasSelectedStates = selectedStateIds.length > 0;
        const canSelectOrganizations = hasSelectedPrograms && hasSelectedStates;
        const organizationDisabledPlaceholder = !hasSelectedPrograms && !hasSelectedStates
            ? 'Select at least one program and state first…'
            : (!hasSelectedPrograms
                ? 'Select at least one program first…'
                : 'Select at least one state first…');

        setPickerRestriction(
            organizationPicker,
            canSelectOrganizations
                ? allowedOrganizationIds(
                    organizationProgramMap,
                    organizationProgramScopeModeMap,
                    organizationStateMap,
                    selectedProgramIds,
                    selectedStateIds,
                    selectedScopeMode
                )
                : [],
            !canSelectOrganizations,
            organizationDisabledPlaceholder
        );

        setPickerRestriction(
            teamPicker,
            allowedIds(teamProgramMap, teamProgramScopeModeMap, selectedProgramIds, selectedScopeMode, false),
            !hasSelectedPrograms,
            'Select at least one program first…'
        );

        setPickerRestriction(
            userPicker,
            allowedIds(userProgramMap, userProgramScopeModeMap, selectedProgramIds, selectedScopeMode, false),
            !hasSelectedPrograms,
            'Select at least one program first…'
        );

        if (organizationsSection) {
            organizationsSection.dispatchEvent(new CustomEvent('agreement-scope:change', {
                bubbles: true,
            }));
        }

        if (membershipSection) {
            membershipSection.dataset.programAllowedUserIds = JSON.stringify(allowedIds(userProgramMap, userProgramScopeModeMap, selectedProgramIds, selectedScopeMode, false));
            membershipSection.dispatchEvent(new CustomEvent('agreement-scope:change', {
                bubbles: true,
            }));
        }

        document.dispatchEvent(new CustomEvent('agreement-scope:change', {
            detail: {
                selectedProgramIds: selectedProgramIds,
            },
        }));
    }

    programPicker.addEventListener('token-picker:change', refreshAgreementScope);
    document.querySelectorAll('input[name="program_scope_mode"]').forEach(function (input) {
        input.addEventListener('change', refreshAgreementScope);
    });
    if (statePicker) {
        statePicker.addEventListener('token-picker:change', refreshAgreementScope);
    }
    refreshAgreementScope();
});

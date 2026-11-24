const form = document.querySelector('[data-activity-form]');

if (form) {
    const {
        agreementConfigs,
        organizationConfigs,
        contactFamilyAdditionalTimeMap,
        participantDirectory,
        historicalParticipantIds,
        initialStateIds,
        initialOrganizationIds,
        initialProjectIds,
        initialProgramIds,
        initialAgreementIds,
        visibleStateIds,
        visibleOrganizationIds,
        visibleProjectIds,
        visibleProgramIds,
        programUserIdsByProgramId,
        initialParticipantTimes,
        initialCompletionCount,
        initialAllottedDurationHours,
        initialAllottedDurationDays,
        isEditMode,
        savedActivityTypeId,
        savedSubActivityId,
        savedAllottedDurationHours,
        savedAllottedDurationDays,
        selectedActivityTypeId,
        legacySubActivityExemption,
    } = JSON.parse(form.querySelector('[data-activity-form-config]').textContent);
    let agreementsPickerInitialized = false;
    let programsPickerInitialized = false;
    let preserveInitialCoverageSelections = isEditMode;
    let coverageInteractionDetected = false;

    // Files and submissions this user cannot edit are not sent with the form, so the prefilled copy cannot include them.
    function droppedFromCopy() {
        const attachmentCount = form.querySelectorAll('[data-logging-document-file]:not(.d-none)').length;
        const lockedSubmissionCount = form.querySelectorAll('[data-submission-locked]').length;
        const plural = (count, noun) => count + ' ' + noun + (count === 1 ? '' : 's');

        return [
            attachmentCount ? plural(attachmentCount, 'attachment') : null,
            lockedSubmissionCount ? plural(lockedSubmissionCount, 'certification submission') + ' you cannot edit' : null,
        ].filter(Boolean).join(' and ');
    }

    // On edit, the save confirmation below lists what the copy drops, so only create asks here.
    form.addEventListener('click', async (event) => {
        const button = event.target.closest('button[name="save_and_duplicate"]');
        const dropped = button && !isEditMode ? droppedFromCopy() : '';

        if (!dropped) {
            return;
        }

        event.preventDefault();
        const confirmed = await window.appConfirm({
            title: 'Some items will not be copied',
            message: 'This activity will be saved as is, but ' + dropped + ' will not carry over to the new copy.',
            action: 'Save & Duplicate',
            cancelLabel: 'Keep editing',
            icon: 'bi-files',
            tone: 'primary',
            trigger: button,
        });

        if (confirmed) {
            form.requestSubmit(button);
        }
    });

    // Creating only adds progress; any edit can move it, so confirm once before an edit saves.
    let editSaveConfirmed = false;
    document.addEventListener('submit', async (event) => {
        if (!isEditMode || event.target !== form || editSaveConfirmed) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        const submitter = event.submitter;
        const dropped = submitter?.name === 'save_and_duplicate' ? droppedFromCopy() : '';
        const impact = 'Edits can change progress on the deliverables and certificates this activity counts toward.';
        editSaveConfirmed = await window.appConfirm({
            title: 'Save changes to this activity?',
            message: dropped ? '' : impact,
            details: dropped ? [impact, 'The new copy will not include ' + dropped + '.'] : [],
            action: dropped ? 'Save & Duplicate' : 'Save changes',
            cancelLabel: 'Keep editing',
            tone: 'warning',
            trigger: submitter,
        });

        if (editSaveConfirmed) {
            form.requestSubmit(submitter && submitter.isConnected ? submitter : undefined);
        }
    }, true);

    function selectedValues(name) {
        return Array.from(form.querySelectorAll('input[type="hidden"][name="' + name + '"]')).map(i => i.value);
    }

    function selectedAgreementIds() {
        const values = selectedValues('agreement_ids[]').map(String);

        if (values.length || agreementsPickerInitialized || initialAgreementIds.length === 0) {
            return values;
        }

        return initialAgreementIds;
    }

    function firstSelectedAgreementId() {
        return selectedAgreementIds()[0] || null;
    }

    function selectedAgreementConfigs() {
        return selectedAgreementIds().map(function (agreementId) {
            return agreementConfigs[agreementId] || null;
        }).filter(Boolean);
    }

    function uniqueMergedProgramUserIds() {
        const merged = new Set();

        resolvedSelectedProgramIds().forEach(function (programId) {
            (programUserIdsByProgramId[String(programId)] || []).forEach(function (value) {
                merged.add(String(value));
            });
        });

        return Array.from(merged);
    }

    function resolvedSelectedProgramIds() {
        const values = selectedProgramIds();

        if (values.length || programsPickerInitialized || initialProgramIds.length === 0) {
            return values;
        }

        return initialProgramIds.map(String);
    }

    function allowedProgramParticipantIds() {
        return resolvedSelectedProgramIds().length ? uniqueMergedProgramUserIds() : [];
    }

    function isHistoricalParticipant(userId) {
        const id = String(userId);

        if (!historicalParticipantIds.includes(id)) {
            return false;
        }

        return allowedProgramParticipantIds().indexOf(id) === -1;
    }

    function uniqueMergedIds(key) {
        const merged = new Set();

        selectedAgreementConfigs().forEach(function (config) {
            (config[key] || []).forEach(function (value) {
                merged.add(String(value));
            });
        });

        return Array.from(merged);
    }

    function selectedStateIds() {
        return selectedValues('state_ids[]').map(String);
    }

    function selectedOrganizationIds() {
        return selectedValues('organization_ids[]').map(String);
    }

    function selectedProjectIds() {
        return selectedValues('project_ids[]').map(String);
    }

    function selectedProgramIds() {
        return selectedValues('program_ids[]').map(String);
    }

    function intersectionValues(values, allowedValues) {
        const allowedSet = new Set((allowedValues || []).map(String));

        return Array.from(new Set((values || []).map(String))).filter(function (value) {
            return allowedSet.has(String(value));
        });
    }

    function mergePreservedCoverage(allowedValues, preservedValues) {
        const normalizedAllowed = Array.isArray(allowedValues) ? allowedValues.map(String) : [];

        if (!preserveInitialCoverageSelections) {
            return normalizedAllowed;
        }

        return Array.from(new Set(normalizedAllowed.concat((preservedValues || []).map(String))));
    }

    function parseJson(text, fallback) {
        try {
            return JSON.parse(text || '');
        } catch (error) {
            return fallback;
        }
    }

    const autoSelectSignatures = {};

    function pickerIsDisabled(picker) {
        return !!picker.querySelector('[data-token-control].token-picker-control-disabled');
    }

    // Fills a coverage picker only when its allowed set has just changed and narrowed to a single
    // option, so a manually cleared token is not immediately re-added.
    function autoSelectSoleOption(key, picker, allowedIds, currentIds) {
        // Restriction passes that run before the pickers boot cannot be trusted to report selections.
        if (!picker || picker.dataset.tokenPickerInitialized !== 'true') {
            return;
        }

        const normalized = Array.from(new Set((allowedIds || []).map(String))).sort();
        const signature = normalized.join('|');

        if (autoSelectSignatures[key] === signature) {
            return;
        }

        autoSelectSignatures[key] = signature;

        if (normalized.length !== 1 || (currentIds || []).length > 0 || pickerIsDisabled(picker)) {
            return;
        }

        picker.dispatchEvent(new CustomEvent('token-picker:set', { detail: normalized }));
    }

    function scopeSectionElement() {
        return form.querySelector('[data-scope-id="activity-coverage-scope"]');
    }

    function allowedScopeProgramIds() {
        const scopeSection = scopeSectionElement();

        if (!scopeSection) {
            return [];
        }

        const projectProgramMap = parseJson(scopeSection.dataset.projectProgramMap, {});
        const agreementProgramIds = new Set(mergePreservedCoverage(
            selectedAgreementIds().length ? uniqueMergedIds('program_ids') : [],
            initialProgramIds
        ));
        const allowed = new Set();

        selectedProjectIds().forEach(function (projectId) {
            (projectProgramMap[String(projectId)] || []).forEach(function (programId) {
                if (agreementProgramIds.has(String(programId))) {
                    allowed.add(String(programId));
                }
            });
        });

        return Array.from(allowed);
    }

    function autoSelectSoleProgram() {
        autoSelectSoleOption(
            'programs',
            document.getElementById('activity-coverage-scope-programs'),
            allowedScopeProgramIds(),
            selectedProgramIds()
        );
    }

    function noteCoverageInteraction(event) {
        if (event.isTrusted) {
            coverageInteractionDetected = true;
        }
    }

    function stopPreservingInitialCoverageIfNeeded() {
        if (coverageInteractionDetected) {
            preserveInitialCoverageSelections = false;
            coverageInteractionDetected = false;
        }
    }

    function joinNames(items) {
        const names = items.map(function (item) {
            return item.name;
        }).filter(Boolean);

        if (names.length === 0) {
            return '';
        }

        if (names.length === 1) {
            return names[0];
        }

        if (names.length === 2) {
            return names[0] + ' and ' + names[1];
        }

        return names.slice(0, -1).join(', ') + ', and ' + names[names.length - 1];
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function joinLabels(labels) {
        const names = (labels || []).filter(Boolean);
        const formattedNames = names.map(function (name) {
            return '<strong>' + escapeHtml(name) + '</strong>';
        });

        if (names.length === 0) {
            return '';
        }

        if (names.length === 1) {
            return formattedNames[0];
        }

        if (names.length === 2) {
            return formattedNames[0] + ' and ' + formattedNames[1];
        }

        return formattedNames.slice(0, -1).join(', ') + ', and ' + formattedNames[formattedNames.length - 1];
    }

    function setFieldContainerDisabled(container, disabled) {
        container.querySelectorAll('input, textarea, select').forEach(function (field) {
            field.disabled = disabled;
        });
    }

    function loggingFieldMatchesPrograms(fieldItem, programIds) {
        const scopedProgramIds = parseJson(fieldItem.dataset.scopeProgramIds, []).map(String);

        if (scopedProgramIds.length === 0) {
            return true;
        }

        if ((programIds || []).length === 0) {
            return false;
        }

        return scopedProgramIds.some(function (programId) {
            return programIds.includes(String(programId));
        });
    }

    function updateLoggingScopeWarning(group, hiddenLabels) {
        const warning = group.querySelector('[data-logging-scope-warning]');
        const text = warning?.querySelector('[data-logging-scope-warning-text]');
        const labels = Array.from(new Set((hiddenLabels || []).filter(Boolean)));

        if (!warning || !text) {
            return;
        }

        if (labels.length === 0) {
            warning.classList.add('d-none');
            text.innerHTML = '';
            return;
        }

        text.innerHTML = joinLabels(labels) + ' ' + (labels.length === 1 ? 'is' : 'are') + ' not required under the current program selection.';
        warning.classList.remove('d-none');
    }

    function applyLoggingFieldProgramScope(group) {
        const programIds = selectedProgramIds();
        const hiddenLabels = [];

        group.querySelectorAll('[data-logging-field-item]').forEach(function (fieldItem) {
            const visible = loggingFieldMatchesPrograms(fieldItem, programIds);

            fieldItem.classList.toggle('d-none', !visible);
            setFieldContainerDisabled(fieldItem, !visible);

            if (!visible && fieldItem.dataset.fieldLabel) {
                hiddenLabels.push(fieldItem.dataset.fieldLabel);
            }
        });

        updateLoggingScopeWarning(group, hiddenLabels);
    }

    function restrictStatePicker() {
        const statePicker = document.getElementById('activity-states-picker');
        const agreements = selectedAgreementIds();
        const allowedStateIds = agreements.length ? uniqueMergedIds('state_ids') : [];

        if (!statePicker) {
            return;
        }

        const restrictedStateIds = mergePreservedCoverage(allowedStateIds, initialStateIds);

        statePicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: agreements.length === 0 || allowedStateIds.length === 0,
                placeholder: agreements.length === 0
                    ? 'Select at least one agreement first…'
                    : allowedStateIds.length === 0
                    ? 'No states are available from the selected agreements.'
                    : 'Search states…',
            }
        }));
        statePicker.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: restrictedStateIds }));
        autoSelectSoleOption('states', statePicker, restrictedStateIds, selectedStateIds());
    }

    function restrictOrganizationPicker() {
        const orgPicker = document.getElementById('activity-organizations-picker');
        const agreements = selectedAgreementIds();
        const states = selectedStateIds();

        if (!orgPicker) {
            return;
        }

        const agreementOrganizationIds = agreements.length ? uniqueMergedIds('organization_ids') : [];
        const allowedOrganizationIds = agreementOrganizationIds.filter(function (organizationId) {
                const config = organizationConfigs[String(organizationId)] || {};

                return states.length === 0 || intersectionValues(config.state_ids || [], states).length > 0;
            });

        const restrictedOrganizationIds = mergePreservedCoverage(allowedOrganizationIds, initialOrganizationIds);

        orgPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: agreements.length === 0 || allowedOrganizationIds.length === 0,
                placeholder: agreements.length === 0
                    ? 'Select at least one agreement first…'
                    : 'No organizations match the selected states.',
            }
        }));
        orgPicker.dispatchEvent(new CustomEvent('token-picker:restrict', {
            detail: restrictedOrganizationIds
        }));
        autoSelectSoleOption('organizations', orgPicker, restrictedOrganizationIds, selectedOrganizationIds());
    }

    function restrictParticipantPicker() {
        const programs = resolvedSelectedProgramIds();
        const participantPicker = document.getElementById('activity-participants-picker');

        if (!participantPicker) return;

        const allowedParticipantIds = allowedProgramParticipantIds();
        const preservedHistoricalIds = historicalParticipantIds.filter(function (id) {
            return allowedParticipantIds.indexOf(String(id)) === -1;
        });
        const restrictedIds = Array.from(new Set(allowedParticipantIds.concat(preservedHistoricalIds).map(String)));
        const disabled = restrictedIds.length === 0;
        let placeholder = 'Search covered users…';

        if (programs.length === 0) {
            placeholder = preservedHistoricalIds.length === 0
                ? 'Select at least one program first…'
                : 'Search historical participants…';
        } else if (allowedParticipantIds.length === 0 && preservedHistoricalIds.length === 0) {
            placeholder = 'No covered users for selected programs.';
        }

        const historicalContexts = {};
        restrictedIds.forEach(function (userId) {
            historicalContexts[String(userId)] = isHistoricalParticipant(userId) ? 'Historical' : '';
        });

        participantPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: disabled,
                placeholder: placeholder,
            }
        }));
        participantPicker.dispatchEvent(new CustomEvent('token-picker:update-option-contexts', {
            detail: historicalContexts,
        }));
        participantPicker.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: restrictedIds }));
    }

    function eligibleFundingTokens(agreementId, role) {
        const config = agreementConfigs[String(agreementId)];

        if (!config) {
            return [];
        }

        const tokens = [];

        if (role === 'payor') {
            (config.payor_organization_ids || []).forEach(function (id) {
                tokens.push('organization:' + id);
            });
        } else {
            (config.payee_organization_ids || []).forEach(function (id) {
                tokens.push('organization:' + id);
            });

            (config.payee_member_user_ids || []).forEach(function (id) {
                tokens.push('user:' + id);
            });
        }

        return tokens;
    }

    function restrictFundingSourcePickers() {
        const selectedAgreements = new Set(selectedAgreementIds());

        document.querySelectorAll('[data-funding-source-picker]').forEach(function (wrapper) {
            const agreementId = wrapper.dataset.agreementId;
            const role = wrapper.dataset.fundingRole;
            const picker = wrapper.querySelector('[data-token-picker]');
            const emptyNotice = document.querySelector('[data-funding-empty-notice="' + agreementId + '-' + role + '"]');
            const config = agreementConfigs[String(agreementId)] || {};
            const requiresRole = role === 'payor' ? config.require_payor : config.require_payee;

            if (!picker) {
                return;
            }

            if (!requiresRole || !selectedAgreements.has(String(agreementId))) {
                picker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
                    detail: {
                        disabled: true,
                        placeholder: 'Not enabled for this agreement.',
                    },
                }));
                emptyNotice?.classList.add('d-none');

                return;
            }

            const eligible = eligibleFundingTokens(agreementId, role);
            const roleLabel = role === 'payor' ? 'payor' : 'payee';

            if (eligible.length === 0) {
                picker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
                    detail: {
                        disabled: true,
                        placeholder: role === 'payor'
                            ? 'No payor organizations linked to KFS accounts on this agreement.'
                            : 'No payee sources with PO numbers on this agreement.',
                    },
                }));
                emptyNotice?.classList.remove('d-none');
            } else {
                picker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
                    detail: {
                        disabled: false,
                        placeholder: 'Search ' + roleLabel + ' sources…',
                    },
                }));
                emptyNotice?.classList.add('d-none');
            }

            picker.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: eligible }));
        });
    }

    function selectedParticipantIds() {
        return selectedValues('participant_user_ids[]').map(String);
    }

    function participantTimeRowsContainer() {
        return document.getElementById('participant-time-rows');
    }

    function participantLabel(userId) {
        return participantDirectory[String(userId)]?.label || ('User #' + userId);
    }

    function buildParticipantTimeInput(name, value, hidden, dataAttribute) {
        const input = document.createElement('input');
        input.type = 'number';
        input.step = '0.25';
        input.min = '0';
        input.className = 'form-control form-control-sm';
        input.name = name;
        input.value = value ?? '';

        if (dataAttribute) {
            input.setAttribute(dataAttribute, '');
        }

        if (hidden) {
            input.classList.add('d-none');
        }

        return input;
    }

    function collectParticipantTimesFromDom() {
        const rows = participantTimeRowsContainer();

        if (!rows) {
            return {};
        }

        const values = {};

        rows.querySelectorAll('[data-participant-time-row]').forEach(function (row) {
            const userId = String(row.dataset.userId || '');

            if (!userId) {
                return;
            }

            values[userId] = {
                user_id: userId,
                hours: row.querySelector('[data-participant-time-hours]')?.value || '',
                prep_hours: row.querySelector('[data-participant-time-prep-hours]')?.value || '0',
                follow_up_hours: row.querySelector('[data-participant-time-follow-up-hours]')?.value || '0',
                participant_name: row.dataset.participantName || participantLabel(userId),
            };
        });

        return values;
    }

    function renderParticipantTimeRows() {
        const rows = participantTimeRowsContainer();
        const emptyState = document.getElementById('participant-time-empty-state');
        const copyButton = document.getElementById('copy-contact-time-to-users');

        if (!rows) {
            return;
        }

        const currentValues = Object.assign({}, initialParticipantTimes, collectParticipantTimesFromDom());
        const selectedIds = selectedParticipantIds();
        const tracksAdditionalTime = !!contactFamilyAdditionalTimeMap[String(document.getElementById('contact_family_id')?.value || '')];

        rows.innerHTML = '';

        selectedIds.forEach(function (userId) {
            const rowData = currentValues[String(userId)] || {};
            const row = document.createElement('tr');
            const nameCell = document.createElement('td');
            const prepCell = document.createElement('td');
            const hoursCell = document.createElement('td');
            const followUpCell = document.createElement('td');
            const labelWrap = document.createElement('div');
            const label = document.createElement('span');
            const historicalBadge = document.createElement('span');
            const userInput = document.createElement('input');
            const prepHoursInput = buildParticipantTimeInput('participant_times[' + userId + '][prep_hours]', rowData.prep_hours ?? 0, !tracksAdditionalTime, 'data-participant-time-prep-hours');
            const hoursInput = buildParticipantTimeInput('participant_times[' + userId + '][hours]', rowData.hours ?? '', false, 'data-participant-time-hours');
            const followUpHoursInput = buildParticipantTimeInput('participant_times[' + userId + '][follow_up_hours]', rowData.follow_up_hours ?? 0, !tracksAdditionalTime, 'data-participant-time-follow-up-hours');

            row.dataset.userId = String(userId);
            row.dataset.participantName = rowData.participant_name || participantLabel(userId);
            row.setAttribute('data-participant-time-row', '');

            labelWrap.className = 'd-flex flex-wrap align-items-center gap-2';
            label.textContent = row.dataset.participantName;
            historicalBadge.className = 'badge app-tag';
            historicalBadge.textContent = 'Historical';
            historicalBadge.classList.toggle('d-none', !isHistoricalParticipant(userId));

            userInput.type = 'hidden';
            userInput.name = 'participant_times[' + userId + '][user_id]';
            userInput.value = userId;

            labelWrap.appendChild(label);
            labelWrap.appendChild(historicalBadge);
            nameCell.appendChild(labelWrap);
            nameCell.appendChild(userInput);

            prepCell.classList.toggle('d-none', !tracksAdditionalTime);
            followUpCell.classList.toggle('d-none', !tracksAdditionalTime);
            prepHoursInput.disabled = !tracksAdditionalTime;
            followUpHoursInput.disabled = !tracksAdditionalTime;
            prepCell.appendChild(prepHoursInput);
            hoursCell.appendChild(hoursInput);
            followUpCell.appendChild(followUpHoursInput);

            row.appendChild(nameCell);
            row.appendChild(prepCell);
            row.appendChild(hoursCell);
            row.appendChild(followUpCell);
            rows.appendChild(row);
        });

        if (emptyState) {
            emptyState.classList.toggle('d-none', selectedIds.length > 0);
        }

        if (copyButton) {
            copyButton.disabled = selectedIds.length === 0;
        }
    }

    function copyContactTimeToParticipants() {
        const contactActivityTime = document.getElementById('contact_time_activity_hours')?.value;
        const contactPrepTime = document.getElementById('contact_time_prep_hours')?.value || '0';
        const contactFollowUpTime = document.getElementById('contact_time_follow_up_hours')?.value || '0';
        const tracksAdditionalTime = !!contactFamilyAdditionalTimeMap[String(document.getElementById('contact_family_id')?.value || '')];

        if (!contactActivityTime) {
            return;
        }

        participantTimeRowsContainer()?.querySelectorAll('[data-participant-time-row]').forEach(function (row) {
            const hoursInput = row.querySelector('[data-participant-time-hours]');
            const prepInput = row.querySelector('[data-participant-time-prep-hours]');
            const followUpInput = row.querySelector('[data-participant-time-follow-up-hours]');

            if (hoursInput) {
                hoursInput.value = contactActivityTime;
            }

            if (tracksAdditionalTime && prepInput) {
                prepInput.value = contactPrepTime;
            }

            if (tracksAdditionalTime && followUpInput) {
                followUpInput.value = contactFollowUpTime;
            }
        });
    }

    function updateAdditionalContactTimeFields() {
        const familyId = String(document.getElementById('contact_family_id')?.value || '');
        const tracksAdditionalTime = !!contactFamilyAdditionalTimeMap[familyId];

        document.querySelectorAll('[data-contact-additional-time-field]').forEach(function (fieldWrap) {
            const input = fieldWrap.querySelector('input');
            fieldWrap.classList.toggle('d-none', !tracksAdditionalTime);

            if (input) {
                input.disabled = !tracksAdditionalTime;
            }

            if (input && tracksAdditionalTime && input.value === '') {
                input.value = '0';
            }
        });

        document.querySelectorAll('[data-participant-extra-header]').forEach(function (header) {
            header.classList.toggle('d-none', !tracksAdditionalTime);
        });
    }

    function restrictScopePickers() {
        const agreements = selectedAgreementIds();
        const scopeSection = scopeSectionElement();
        const projectPicker = document.getElementById('activity-coverage-scope-projects');

        if (!scopeSection || !projectPicker) return;

        const allowedProjectIds = agreements.length ? uniqueMergedIds('project_ids') : [];
        const allowedProgramIds = agreements.length ? uniqueMergedIds('program_ids') : [];
        const disableProjects = agreements.length === 0 || allowedProjectIds.length === 0;
        const projectPlaceholder = agreements.length === 0
            ? 'Select at least one agreement first…'
            : 'No projects are available from the selected agreements.';
        const forceProgramDisabled = agreements.length === 0 || allowedProgramIds.length === 0;
        const programDisabledPlaceholder = agreements.length === 0
            ? 'Select at least one agreement first…'
            : 'No programs are available from the selected agreements.';

        const restrictedProjectIds = mergePreservedCoverage(allowedProjectIds, initialProjectIds);

        projectPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: disableProjects,
                placeholder: projectPlaceholder,
            }
        }));
        projectPicker.dispatchEvent(new CustomEvent('token-picker:restrict', {
            detail: restrictedProjectIds
        }));
        scopeSection.dispatchEvent(new CustomEvent('project-program-scope:restrict', {
            detail: {
                programIds: mergePreservedCoverage(allowedProgramIds, initialProgramIds),
                forceProjectDisabled: disableProjects,
                forceProgramDisabled: forceProgramDisabled,
                projectDisabledPlaceholder: projectPlaceholder,
                programDisabledPlaceholder: programDisabledPlaceholder,
            }
        }));
        autoSelectSoleOption('projects', projectPicker, restrictedProjectIds, selectedProjectIds());
        autoSelectSoleProgram();
    }

    function restrictAgreementPicker() {
        const agreementPicker = document.getElementById('activity-agreements-picker');

        if (!agreementPicker) {
            return;
        }

        agreementPicker.dispatchEvent(new CustomEvent('token-picker:set-disabled', {
            detail: {
                disabled: false,
                placeholder: 'Search agreements…',
            }
        }));
        agreementPicker.dispatchEvent(new CustomEvent('token-picker:restrict', {
            detail: mergePreservedCoverage(Object.keys(agreementConfigs), initialAgreementIds)
        }));
    }

    function restrictClassificationOptions() {
        const family = document.getElementById('contact_family_id');
        const type = document.getElementById('activity_type_id');
        const selectedType = document.getElementById('activity_type_selected');
        const agreements = selectedAgreementIds();

        if (!family || !type) return;

        const hasAgreementSelection = agreements.length > 0;
        const allowedFamilyIds = new Set(uniqueMergedIds('contact_family_ids'));
        const hasRestriction = hasAgreementSelection && allowedFamilyIds.size > 0;

        if (!hasAgreementSelection) {
            family.value = '';
            family.disabled = true;
            if (selectedType) {
                selectedType.value = '';
            }
            if (family.options.length > 0) {
                family.options[0].textContent = 'Select at least one agreement first…';
            }
            type.innerHTML = '<option value="">Select agreement first…</option>';
            type.disabled = true;
            updateContactFamilyLoggingGroups();
            updateActivityLoggingGroups();
            updateAdditionalContactTimeFields();
            return;
        }

        family.disabled = allowedFamilyIds.size === 0;

        Array.from(family.options).forEach(function (option) {
            if (!option.value) {
                option.disabled = false;
                option.hidden = false;
                option.textContent = hasAgreementSelection && allowedFamilyIds.size === 0
                    ? 'No activity families available for selected agreements.'
                    : 'Select family…';
                return;
            }

            const allowed = !hasRestriction || allowedFamilyIds.has(String(option.value));
            option.disabled = !allowed;
            option.hidden = !allowed;
        });

        if (hasRestriction && family.value && !allowedFamilyIds.has(String(family.value))) {
            family.value = '';
            if (selectedType) {
                selectedType.value = '';
            }
            updateContactFamilyLoggingGroups();
            updateActivityLoggingGroups();
        }

        if (hasAgreementSelection && allowedFamilyIds.size === 0) {
            family.value = '';
            if (selectedType) {
                selectedType.value = '';
            }
            type.innerHTML = '<option value="">No activity types available for selected agreements.</option>';
            updateContactFamilyLoggingGroups();
            updateActivityLoggingGroups();
        }

        updateActivityTypeState();
    }

    function refreshActivityTypes() {
        const family = document.getElementById('contact_family_id');
        const type = document.getElementById('activity_type_id');

        if (!family || !type) return;

        if (!family.value) {
            type.innerHTML = '<option value="">Select family first…</option>';
            type.disabled = true;
            refreshSubActivities();
            return;
        }

        window.htmx.trigger(family, 'change');
    }

    function updateLoggingFieldsSectionVisibility() {
        const section = document.getElementById('logging-fields-section');

        if (!section) {
            return;
        }

        const visible = Array.from(section.querySelectorAll(
            '[data-contact-family-logging-group], [data-activity-logging-group], [data-sub-activity-logging-group], [data-agreement-logging-group]'
        )).some(function (group) {
            return !group.classList.contains('d-none');
        });

        section.classList.toggle('d-none', !visible);
    }

    function updateAgreementLoggingGroups() {
        const selected = new Set(selectedAgreementIds());

        document.querySelectorAll('[data-agreement-logging-group]').forEach(function (group) {
            const visible = selected.has(group.dataset.agreementLoggingGroup);
            group.classList.toggle('d-none', !visible);

            if (!visible) {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = true;
                });
            } else {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = false;
                });
                applyLoggingFieldProgramScope(group);
            }
        });

        updateLoggingFieldsSectionVisibility();
        restrictFundingSourcePickers();
    }

    function updateTimeTrackingSection() {
        const selected = selectedAgreementConfigs();
        const section = document.getElementById('time-tracking-section');
        const contactGroup = section?.querySelector('[data-time-tracking-subsection="contact"]');
        const userGroup = section?.querySelector('[data-time-tracking-subsection="user"]');
        const contactRequiredBy = selected.filter(function (config) {
            return config.time_tracking_mode === 'by_contact' || config.time_tracking_mode === 'by_user';
        });
        const userRequiredBy = selected.filter(function (config) {
            return config.time_tracking_mode === 'by_user';
        });
        const hasTimeTracking = contactRequiredBy.length > 0;

        if (contactGroup) {
            contactGroup.classList.toggle('d-none', !hasTimeTracking);
            const label = contactGroup.querySelector('.form-subsection-meta');
            if (label) {
                label.textContent = hasTimeTracking
                    ? 'Required by: ' + joinNames(contactRequiredBy)
                    : '';
                label.classList.toggle('d-none', !label.textContent);
            }
        }

        if (userGroup) {
            const hasUserTracking = userRequiredBy.length > 0;
            userGroup.classList.toggle('d-none', !hasUserTracking);
            const label = userGroup.querySelector('.form-subsection-meta');
            if (label) {
                label.textContent = hasUserTracking
                    ? 'Required by: ' + joinNames(userRequiredBy)
                    : '';
                label.classList.toggle('d-none', !label.textContent);
            }
        }

        updateAdditionalContactTimeFields();
        renderParticipantTimeRows();
    }

    function updateContactFamilyLoggingGroups() {
        const familyId = document.getElementById('contact_family_id')?.value;

        document.querySelectorAll('[data-contact-family-logging-group]').forEach(function (group) {
            const visible = group.dataset.contactFamilyLoggingGroup === familyId;
            group.classList.toggle('d-none', !visible);

            if (!visible) {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = true;
                });
            } else {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = false;
                });
                applyLoggingFieldProgramScope(group);
            }
        });

        updateLoggingFieldsSectionVisibility();
    }

    function updateActivityLoggingGroups() {
        const activityTypeId = document.getElementById('activity_type_id')?.value;

        document.querySelectorAll('[data-activity-logging-group], [data-sub-activity-logging-group]').forEach(function (group) {
            const visible = group.dataset.subActivityLoggingGroup !== undefined
                ? group.dataset.subActivityLoggingGroup === document.getElementById('sub_activity_id')?.value
                : group.dataset.activityLoggingGroup === activityTypeId;
            group.classList.toggle('d-none', !visible);

            if (!visible) {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = true;
                });
            } else {
                group.querySelectorAll('input, textarea, select').forEach(function (field) {
                    field.disabled = false;
                });
                applyLoggingFieldProgramScope(group);
            }
        });

        updateLoggingFieldsSectionVisibility();
        updateAllottedDurationDisplay();
    }

    function formatDurationValue(hours, days) {
        if (days && parseFloat(days) > 0) {
            const value = parseFloat(days);
            return value + ' ' + (value === 1 ? 'day' : 'days');
        }

        if (hours && parseFloat(hours) > 0) {
            const value = parseFloat(hours);
            return value + ' ' + (value === 1 ? 'hour' : 'hours');
        }

        return '';
    }

    function durationFromActivityTypeOption(option) {
        if (!option || !option.value) {
            return { hours: null, days: null };
        }

        const hours = parseFloat(option.dataset.durationHours || '');
        const days = parseFloat(option.dataset.durationDays || '');

        return {
            hours: Number.isFinite(hours) && hours > 0 ? hours : null,
            days: Number.isFinite(days) && days > 0 ? days : null,
        };
    }

    function updateAllottedDurationDisplay(forceSnapshot) {
        const displayInput = document.getElementById('allotted_duration_display');
        const typeSelect = document.getElementById('activity_type_id');

        if (!displayInput) {
            return;
        }

        let hours = null;
        let days = null;

        const subSelect = document.getElementById('sub_activity_id');
        const subOption = subSelect?.value ? subSelect.options[subSelect.selectedIndex] : null;
        const unchanged = isEditMode && typeSelect?.value === savedActivityTypeId && (subSelect?.value || '') === savedSubActivityId;
        if (forceSnapshot || unchanged) {
            hours = initialAllottedDurationHours;
            days = initialAllottedDurationDays;
        } else if (typeSelect && typeSelect.value) {
            const duration = durationFromActivityTypeOption(subOption || typeSelect.options[typeSelect.selectedIndex]);
            hours = duration.hours;
            days = duration.days;
        }

        const formatted = formatDurationValue(hours, days);
        displayInput.value = formatted;

        const selectedOption = typeSelect && typeSelect.value ? typeSelect.options[typeSelect.selectedIndex] : null;
        const typeName = selectedOption && selectedOption.text ? selectedOption.text.trim() : '';
        const help = document.getElementById('allotted_duration_help');

        if (!typeName) {
            displayInput.placeholder = 'Select an activity type';
            if (help) {
                help.textContent = '';
            }
            return;
        }

        const effective = durationFromActivityTypeOption(subOption || selectedOption);
        const effectiveLabel = formatDurationValue(effective.hours, effective.days) || 'no duration';
        const oldLabel = formatDurationValue(savedAllottedDurationHours, savedAllottedDurationDays) || 'no duration';
        const changed = isEditMode && (subSelect?.value || '') !== savedSubActivityId;
        const warning = document.getElementById('sub_activity_duration_warning');
        if (warning) {
            warning.textContent = 'The duration will change from ' + oldLabel + ' to ' + effectiveLabel + ', which updates deliverable progress.';
            warning.parentElement.classList.toggle('d-none', !changed || effectiveLabel === oldLabel);
        }
        displayInput.placeholder = formatted ? '' : 'No duration configured for this activity type';
        if (help) {
            help.textContent = formatted
                ? 'Set by ' + typeName + '.'
                : 'No duration configured for ' + typeName + '.';
            if (subOption) {
                const subName = subOption.text.trim();
                const typeDuration = durationFromActivityTypeOption(selectedOption);
                const typeLabel = formatDurationValue(typeDuration.hours, typeDuration.days);
                help.textContent = subOption.dataset.durationSource === 'sub_activity'
                    ? 'Set by sub-activity ' + subName + (typeLabel ? ', overriding ' + typeName + "'s " + typeLabel : '') + '.'
                    : (typeLabel ? 'Inherited from type ' + typeName + '; ' + subName + ' has no duration of its own.' : 'No duration configured for ' + subName + ' or type ' + typeName + '.');
            }
        }
    }

    let previousSubActivityType = selectedActivityTypeId;
    function refreshSubActivities() {
        const type = document.getElementById('activity_type_id');
        const sub = document.getElementById('sub_activity_id');
        const selected = document.getElementById('sub_activity_selected');
        if (type.value !== previousSubActivityType) {
            selected.value = '';
            sub.value = '';
        }
        previousSubActivityType = type.value;
        window.htmx.trigger(sub, 'refresh-sub-activities');
    }
    document.getElementById('sub_activity_id')?.addEventListener('htmx:afterSwap', function () {
        const sub = document.getElementById('sub_activity_id');
        const visible = Array.from(sub.options).some(option => option.value !== '');
        document.getElementById('sub_activity_field').classList.toggle('d-none', !visible);
        sub.disabled = !visible;
        const legacy = legacySubActivityExemption && document.getElementById('activity_type_id').value === savedActivityTypeId;
        sub.required = visible && !legacy;
        document.querySelector('label[for="sub_activity_id"]')?.classList.toggle('required-label', sub.required);
        sub.setAttribute('aria-required', String(sub.required));
        sub.closest('[data-form-field]')?.toggleAttribute('data-field-required', sub.required);
        document.getElementById('sub_activity_selected').value = sub.value;
        updateSelectHelperText('sub_activity_id', 'sub_activity_helper_text');
        updateActivityLoggingGroups();
    });

    function updateActivityTypeState() {
        const family = document.getElementById('contact_family_id');
        const type = document.getElementById('activity_type_id');
        if (!family || !type) return;
        type.disabled = !family.value;
    }

    function updateSelectHelperText(selectId, targetId) {
        const select = document.getElementById(selectId);
        const target = document.getElementById(targetId);

        if (!select || !target) {
            return;
        }

        const selectedOption = select.options[select.selectedIndex];
        const helperText = selectedOption?.dataset?.helperText || '';

        target.textContent = helperText;
        target.classList.toggle('d-none', helperText.trim() === '');
    }

    const agreementsPicker = document.getElementById('activity-agreements-picker');
    if (agreementsPicker) {
        agreementsPicker.addEventListener('token-picker:change', function () {
            if (agreementsPickerInitialized) {
                preserveInitialCoverageSelections = false;
            }
            agreementsPickerInitialized = true;
            restrictStatePicker();
            restrictOrganizationPicker();
            restrictScopePickers();
            restrictParticipantPicker();
            restrictFundingSourcePickers();
            restrictClassificationOptions();
            refreshActivityTypes();
            updateAgreementLoggingGroups();
            updateTimeTrackingSection();
        });
        agreementsPicker.addEventListener('token-picker:initialized', function () {
            agreementsPickerInitialized = true;
            restrictStatePicker();
            restrictOrganizationPicker();
            restrictScopePickers();
            restrictParticipantPicker();
            restrictFundingSourcePickers();
            restrictClassificationOptions();
            refreshActivityTypes();
            updateAgreementLoggingGroups();
            updateTimeTrackingSection();
        });
    }

    document.getElementById('activity-states-picker')?.addEventListener('click', noteCoverageInteraction, true);
    document.getElementById('activity-states-picker')?.addEventListener('input', noteCoverageInteraction, true);
    document.getElementById('activity-states-picker')?.addEventListener('token-picker:change', function () {
        stopPreservingInitialCoverageIfNeeded();
        restrictOrganizationPicker();
    });

    document.getElementById('activity-organizations-picker')?.addEventListener('click', noteCoverageInteraction, true);
    document.getElementById('activity-organizations-picker')?.addEventListener('input', noteCoverageInteraction, true);
    document.getElementById('activity-organizations-picker')?.addEventListener('token-picker:change', function () {
        stopPreservingInitialCoverageIfNeeded();
    });

    form.querySelector('[data-scope-id="activity-coverage-scope"]')?.addEventListener('click', noteCoverageInteraction, true);
    form.querySelector('[data-scope-id="activity-coverage-scope"]')?.addEventListener('input', noteCoverageInteraction, true);
    form.querySelector('[data-scope-id="activity-coverage-scope"]')?.addEventListener('project-program-scope:change', function () {
        programsPickerInitialized = true;
        stopPreservingInitialCoverageIfNeeded();
        autoSelectSoleProgram();
        restrictParticipantPicker();
        updateAgreementLoggingGroups();
        updateContactFamilyLoggingGroups();
        updateActivityLoggingGroups();
    });

    document.getElementById('activity-participants-picker')?.addEventListener('token-picker:change', function () {
        renderParticipantTimeRows();
    });

    document.getElementById('copy-contact-time-to-users')?.addEventListener('click', copyContactTimeToParticipants);

    form.addEventListener('change', function (event) {
        if (event.target && event.target.id === 'contact_family_id') {
            const selectedType = document.getElementById('activity_type_selected');
            if (selectedType && event.isTrusted) selectedType.value = '';
            updateActivityTypeState();
            updateSelectHelperText('contact_family_id', 'contact_family_helper_text');
            updateContactFamilyLoggingGroups();
            updateActivityLoggingGroups();
            updateAdditionalContactTimeFields();
            renderParticipantTimeRows();
        }
        if (event.target && event.target.id === 'sub_activity_id') {
            document.getElementById('sub_activity_selected').value = event.target.value;
            updateSelectHelperText('sub_activity_id', 'sub_activity_helper_text');
            updateActivityLoggingGroups();
        }
        if (event.target && event.target.id === 'activity_type_id') {
            document.getElementById('activity_type_selected').value = event.target.value;
            refreshSubActivities();
            updateSelectHelperText('activity_type_id', 'activity_type_helper_text');
            updateActivityLoggingGroups();
        }
    });

    document.getElementById('contact_family_id')?.addEventListener('htmx:afterRequest', function () {
        const selectedType = document.getElementById('activity_type_selected');
        const type = document.getElementById('activity_type_id');
        if (selectedType && type && selectedType.value) {
            type.value = selectedType.value;
        }
        if (type) {
            const availableOptions = Array.from(type.options).filter(function (option) {
                return option.value !== '';
            });

            if (!type.value && availableOptions.length === 1) {
                type.value = availableOptions[0].value;
            }
        }
        if (type) type.dispatchEvent(new Event('change', { bubbles: true }));
        updateSelectHelperText('activity_type_id', 'activity_type_helper_text');
        updateAllottedDurationDisplay(false);
    });

    const family = document.getElementById('contact_family_id');
    if (family && family.value) window.htmx.trigger(family, 'change');

    updateActivityTypeState();
    restrictStatePicker();
    restrictOrganizationPicker();
    restrictScopePickers();
    restrictAgreementPicker();
    restrictParticipantPicker();
    restrictFundingSourcePickers();
    restrictClassificationOptions();
    updateAgreementLoggingGroups();
    updateTimeTrackingSection();
    updateContactFamilyLoggingGroups();
    updateActivityLoggingGroups();
    updateAdditionalContactTimeFields();
    updateAllottedDurationDisplay(isEditMode);
    updateSelectHelperText('contact_family_id', 'contact_family_helper_text');
    updateSelectHelperText('activity_type_id', 'activity_type_helper_text');
    renderParticipantTimeRows();
}

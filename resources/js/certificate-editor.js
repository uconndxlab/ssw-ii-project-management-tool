const root = document.querySelector('[data-certificate-editor]');

if (root) {
    const form = root.closest('form');
    const $ = (selector, scope = root) => scope.querySelector(selector);
    const $$ = (selector, scope = root) => [...scope.querySelectorAll(selector)];
    const value = (selector, scope = root) => $(selector, scope)?.value ?? '';
    const text = (selector, scope = root) => $(selector, scope)?.selectedOptions?.[0]?.textContent.trim() ?? '';
    const alive = (row) => value(':scope > [data-cert-delete]', row) !== '1';
    const rows = (selector, scope = root) => $$(selector, scope).filter(alive);
    const requirementRows = (scope = root) => rows('[data-cert-requirement]', scope);
    const stageOf = (node) => node.closest('[data-cert-stage]');
    const counters = {};
    const config = { requirement: ['REQ', 'data-cert-requirement'], group: ['GROUP', 'data-cert-group'], role: ['ROLE', 'data-cert-role-row'] };
    Object.entries(config).forEach(([kind, [, attribute]]) => {
        counters[kind] = Math.max(-1, ...$$(`[${attribute}]`).map((node) => Number(node.getAttribute(attribute)))) + 1;
    });
    const announce = (message) => {
        $('[data-cert-status]').textContent = message;
        root.dispatchEvent(new Event('change', { bubbles: true }));
    };
    let roleOpener;

    function navigate(section, focus = false) {
        $$('[data-cert-section]').forEach((pane) => { pane.hidden = pane.dataset.certSection !== section; });
        $$('[data-cert-nav]').forEach((button) => {
            const selected = button.dataset.certNav === section;
            button.classList.toggle('active', selected);
            if (selected) button.setAttribute('aria-current', 'page');
            else button.removeAttribute('aria-current');
        });
        if (section === 'review') renderReview();
        if (focus) $(`[data-cert-nav="${section}"]`).focus();
    }

    function openRequirement(row, focus = false) {
        requirementRows().forEach((item) => {
            const opened = item === row;
            $('[data-cert-editor]', item).hidden = !opened;
            $('[data-cert-edit]', item).setAttribute('aria-expanded', String(opened));
        });
        if (focus) $('[data-cert-kind]', row).focus();
    }

    function newRow(kind, stage, container) {
        const [token] = config[kind];
        const index = counters[kind]++;
        const template = $(`[data-cert-template="${kind}"]`).innerHTML;
        const holder = document.createElement('template');
        holder.innerHTML = template.replaceAll(`__${token}__`, index).replaceAll('__PHASE__', stage?.dataset.certStage ?? 'initial');
        const row = holder.content.firstElementChild;
        container.append(row);
        if (kind === 'requirement') {
            const group = container.closest('[data-cert-group]');
            if (group) row.dataset.pendingGroup = group.dataset.certGroup;
        }
        sync();
        if (kind === 'requirement') openRequirement(row, true);
        else row.querySelector('input:not([type=hidden])')?.focus();
        announce(`${kind === 'role' ? 'Role' : kind === 'group' ? 'Group' : 'Requirement'} added. Save the certificate to keep changes.`);
        return row;
    }

    function markDeleted(row) {
        $(':scope > [data-cert-delete]', row).value = '1';
        row.hidden = true;
    }

    function groupSentence(group) {
        const count = requirementRows($('[data-cert-members]', group)).length;
        const mode = value('[data-cert-group-mode]', group);
        const rule = mode === 'any' ? 'Any one' : mode === 'n_of' ? `At least ${value('[data-cert-required-count]', group) || '?'} of ${count}` : `All ${count}`;
        return `${value('[data-cert-group-label]', group) || 'Unnamed group'} — ${rule} required`;
    }

    function windowSentence(row) {
        const override = value('[data-cert-window]', row);
        const inherited = value('[name=default_window_months]');
        return override ? `Last ${override} months (override)` : inherited ? `Last ${inherited} months (certificate default)` : 'All time (certificate default)';
    }

    function ruleSentence(rule) {
        const labels = $$('[data-cert-options] input:checked', rule).map((input) => input.labels?.[0]?.textContent.trim()).filter(Boolean);
        if (!value('[data-cert-dimension]', rule)) return 'Choose a dimension and categories to finish this rule.';
        const dimension = text('[data-cert-dimension]', rule);
        if (!labels.length) return `Choose categories for ${dimension} to finish this rule.`;
        return value('[data-cert-rule-mode]', rule) === 'quota'
            ? `At least ${value('[data-cert-minimum]', rule) || '?'} submissions with ${dimension}: ${labels.join(' or ') || 'choose categories'}`
            : `Cover every ${dimension}: ${labels.join(', ') || 'choose categories'}`;
    }

    function requirementSentence(row, compact = false) {
        const kind = value('[data-cert-kind]', row);
        if (!kind) return 'Choose Activity work, Tool submissions or Coach confirmation.';
        if (kind === 'attestation') return `Coach confirmation${value('[data-cert-threshold]', row) ? ` · Guidance: ${value('[data-cert-threshold]', row)}` : ''}`;
        const count = value('[data-cert-target]', row) || '?';
        const pieces = [kind === 'tool_submission' ? `${count} ${value('[data-cert-tool]', row) ? text('[data-cert-tool]', row) : 'tool'} submissions${value('[data-cert-tool]', row) ? '' : ' (choose a tool)'}` : `${count} activity completion units`];
        if (kind === 'tool_submission') pieces.push($('[data-cert-passing]', row).checked ? 'Coach-marked passing only' : 'Passing not required');
        pieces.push(text('[data-cert-family]', row), text('[data-cert-type]', row), text('[data-cert-role]', row), windowSentence(row));
        if (compact) {
            const count = rows('[data-cert-rule]', row).length;
            if (count) pieces.push(`${count} coverage/quota ${count === 1 ? 'rule' : 'rules'}`);
            return pieces.join(' · ');
        }
        rows('[data-cert-rule]', row).forEach((rule) => pieces.push(ruleSentence(rule)));
        if (value('[data-cert-threshold]', row)) pieces.push(`Guidance: ${value('[data-cert-threshold]', row)}`);
        return pieces.join(' · ');
    }

    function syncRoles() {
        $$('[data-cert-role]').forEach((select) => {
            const selected = select.dataset.selectedRole ?? select.value;
            $$('option[data-local-role]', select).forEach((option) => option.remove());
            rows('[data-cert-role-row]').forEach((role) => {
                const id = value('[data-cert-saved-role]', role) || `new:${role.dataset.certRoleRow}`;
                const option = new Option(value('[data-cert-role-name]', role) || 'Unnamed role', id);
                option.dataset.localRole = 'true';
                select.add(option);
            });
            if (selected && ![...select.options].some((option) => option.value === selected)) {
                const missing = new Option('Unavailable role — choose another', selected);
                missing.dataset.localRole = 'true';
                select.add(missing);
            }
            select.value = selected;
            delete select.dataset.selectedRole;
            const row = select.closest('[data-cert-requirement]');
            $('[data-cert-role-id]', row).value = selected.startsWith('new:') ? '' : selected;
            $('[data-cert-role-index]', row).value = selected.startsWith('new:') ? selected.slice(4) : '';
        });
    }

    function syncGroups(stage) {
        const groups = rows('[data-cert-group]', stage);
        requirementRows(stage).forEach((row) => {
            const select = $('[data-cert-group-select]', row);
            const selected = row.dataset.pendingGroup ?? select.value;
            delete row.dataset.pendingGroup;
            select.replaceChildren(new Option('Individually required', ''));
            groups.forEach((group) => select.add(new Option(value('[data-cert-group-label]', group) || 'Unnamed group', group.dataset.certGroup)));
            const group = groups.find((item) => item.dataset.certGroup === selected);
            if (selected && !group) select.add(new Option('Unavailable group — choose another', selected));
            select.value = selected;
            const destination = group ? $('[data-cert-members]', group) : $('[data-cert-ungrouped]', stage);
            if (row.parentElement !== destination) destination.append(row);
        });
        groups.forEach((group) => {
            $('[data-cert-group-summary]', group).textContent = groupSentence(group);
            const isCount = value('[data-cert-group-mode]', group) === 'n_of';
            $('[data-cert-group-count]', group).hidden = !isCount;
            $('[data-cert-required-count]', group).disabled = !isCount;
        });
        $('[data-cert-empty]', stage).hidden = requirementRows(stage).length > 0 || groups.length > 0;
    }

    function syncRequirement(row) {
        const kind = value('[data-cert-kind]', row);
        const tool = value('[data-cert-tool]', row);
        $('[data-cert-kind-details]', row).hidden = !kind;
        $$('[data-cert-for]', row).forEach((block) => {
            const shown = block.dataset.certFor.split(' ').includes(kind);
            block.hidden = !shown;
            $$('input, select, textarea, button', block).forEach((input) => { input.disabled = !shown; });
        });
        if (kind === 'attestation') $('[data-cert-target]', row).value = '1';
        // The target is still submitted for confirmations, whose single check is the target.
        $('[data-cert-target]', row).disabled = false;
        $('[data-cert-target-help]', row).textContent = kind === 'activity_count' ? 'Counts recorded completion units, not the number of activities.' : 'Total submissions; coverage and quotas apply within this same set.';
        $('[data-cert-effective-window]', row).textContent = windowSentence(row);
        const mode = value('[name=program_scope_mode]:checked') || value('[name=program_scope_mode]');
        const selectedPrograms = new Set($$('[name="program_ids[]"]').map((input) => input.value));
        const toolSelect = $('[data-cert-tool]', row);
        [...toolSelect.options].forEach((option) => {
            if (!option.value) return;
            const compatible = mode === 'all' ? option.dataset.scopeMode !== 'none' : mode === 'specific' && (option.dataset.scopeMode === 'all' || option.dataset.programIds.split(',').some((id) => selectedPrograms.has(id)));
            option.hidden = !compatible && option.value !== tool;
            option.disabled = !compatible && option.value !== tool;
            option.dataset.compatible = String(compatible);
        });
        $('[data-cert-tool-warning]', row).hidden = !tool || toolSelect.selectedOptions[0]?.dataset.compatible !== 'false';
        const compatibleTools = [...toolSelect.options].some((option) => option.value && option.dataset.compatible === 'true');
        const missingPrograms = mode === 'specific' && !selectedPrograms.size;
        const empty = $('[data-cert-tool-empty]', row);
        empty.hidden = compatibleTools;
        $('[data-cert-tool-empty-message]', row).textContent = missingPrograms
            ? 'Choose the certificate’s programs in Basics to see compatible tools.'
            : 'No submission tools match this scope. Check the programs in Basics or the tool catalog.';
        const hasDimensions = tool && $('[data-cert-rule-template]', row).content.querySelector(`option[data-tool="${tool}"]`);
        $('[data-cert-add=rule]', row).disabled = kind !== 'tool_submission' || !hasDimensions;
        $('[data-cert-add-rule-help]', row).textContent = !tool ? 'Choose a tool before adding coverage or quotas.' : !hasDimensions ? 'This tool has no category dimensions. Its total and passing requirement still apply.' : '';
        rows('[data-cert-rule]', row).forEach((rule) => {
            const dimension = value('[data-cert-dimension]', rule);
            $$('[data-cert-dimension] option[data-tool]', rule).forEach((option) => {
                option.hidden = option.dataset.tool !== tool && option.value !== dimension;
                option.disabled = option.hidden;
            });
            $$('[data-cert-options]', rule).forEach((block) => {
                block.hidden = block.dataset.certOptions !== dimension;
                $$('input', block).forEach((input) => { input.disabled = block.hidden || kind !== 'tool_submission'; });
            });
            const quota = value('[data-cert-rule-mode]', rule) === 'quota';
            $('[data-cert-quota]', rule).hidden = !quota;
            $('[data-cert-minimum]', rule).disabled = !quota || kind !== 'tool_submission';
            $('[data-cert-rule-summary]', rule).textContent = ruleSentence(rule);
            $('[data-cert-category-help]', rule).textContent = quota ? 'At least the minimum number may match any of these categories.' : 'At least one qualifying submission must cover each selected category.';
            $$('[data-cert-options] input', rule).forEach((input) => {
                if (input.closest('[data-cert-options]').hidden && input.checked) {
                    input.closest('[data-cert-options]').hidden = false;
                    input.disabled = kind !== 'tool_submission';
                }
            });
        });
        $('[data-cert-title]', row).textContent = value('[data-cert-label]', row) || ({activity_count: 'Activity work', tool_submission: 'Tool submissions', attestation: 'Coach confirmation'}[kind] ?? 'New requirement');
        $('[data-cert-summary]', row).textContent = requirementSentence(row, true);
    }

    function sync() {
        $$('[data-cert-requirement] input, [data-cert-requirement] select, [data-cert-requirement] textarea, [data-cert-requirement] button, [data-cert-group] input, [data-cert-group] select, [data-cert-group] button, [data-cert-roles] input, [data-cert-roles] button').forEach((input) => { input.disabled = false; });
        syncRoles();
        $$('[data-cert-stage]').forEach(syncGroups);
        requirementRows().forEach(syncRequirement);
        $$('[data-cert-delete]').filter((input) => input.value === '1').forEach((input) => {
            const row = input.parentElement;
            $$('input, select, textarea, button', row).forEach((control) => { control.disabled = control.parentElement !== row || control.type !== 'hidden'; });
        });
        $$('[data-cert-requirement], [data-cert-group]').filter(alive).forEach((row) => {
            const siblings = [...row.parentElement.children].filter((sibling) => sibling.matches('[data-cert-requirement], [data-cert-group]') && alive(sibling));
            const buttons = row.querySelectorAll(':scope > div [data-cert-move]');
            buttons.forEach((button) => { button.disabled = button.dataset.certMove === 'up' ? row === siblings[0] : row === siblings.at(-1); });
        });
        const linked = $('[name=renewal_matches_initial][type=checkbox]').checked;
        $('[data-cert-stage="renewal"]').hidden = linked;
        $('[data-cert-linked-note]').hidden = !linked;
        $('[data-cert-renewal-actions]').hidden = linked;
    }

    function renderReview() {
        const review = $('[data-cert-review]');
        review.replaceChildren();
        const addText = (parent, tag, content, className = '') => {
            const element = document.createElement(tag);
            element.textContent = content;
            element.className = className;
            parent.append(element);
            return element;
        };
        const scopeMode = value('[name=program_scope_mode]:checked') || value('[name=program_scope_mode]');
        const pickerLabels = (pickerId) => {
            const picker = document.getElementById(pickerId);
            if (!picker) return [];
            const selected = new Set($$('input[type=hidden]', picker).map((input) => input.value));
            const options = JSON.parse($('[data-token-options]', picker)?.textContent || '[]');
            return options.filter((option) => selected.has(String(option.value))).map((option) => option.label);
        };
        const scopeText = scopeMode === 'all' ? 'All programs' : scopeMode === 'none' ? 'No programs' : `Selected programs: ${pickerLabels(form.id.includes('edit') ? 'certificate-edit-scope-programs' : 'certificate-create-scope-programs').join(', ') || 'none selected'}`;
        addText(review, 'h3', value('[name=name]') || 'Untitled certificate', 'h5');
        addText(review, 'p', `${scopeText}. Eligible evidence must share a program with this certificate; agreement sponsorship does not limit counting.`, 'small');
        addText(review, 'p', `Award validity: ${value('[name=validity_months]') ? `${value('[name=validity_months]')} months` : 'does not expire'}. Default work lookback: ${value('[name=default_window_months]') ? `${value('[name=default_window_months]')} months` : 'all time'}.`, 'small');
        const prereqInputs = $$('[name="prerequisite_certificate_ids[]"]');
        const prereqNames = pickerLabels('certificate-prerequisites');
        addText(review, 'p', prereqInputs.length ? `Prerequisites (${text('[name=prerequisite_mode]').toLowerCase()}): ${prereqNames.join(', ')}. Award-based eligibility will be checked in the later lifecycle phase.` : 'No prerequisite certificates.', 'small');
        ['initial', 'renewal'].forEach((phase) => {
            addText(review, 'h3', phase === 'initial' ? 'Initial requirements' : 'Renewal requirements', 'h6 mt-4');
            if (phase === 'renewal' && $('[name=renewal_matches_initial][type=checkbox]').checked) {
                addText(review, 'p', 'Follows initial requirements: all rules, groups, filters and windows above remain linked.');
                return;
            }
            const stage = $(`[data-cert-stage="${phase}"]`);
            const addRequirement = (parent, row) => {
                const item = addText(parent, 'li', '', 'mb-2');
                addText(item, 'strong', value('[data-cert-label]', row) || $('[data-cert-title]', row).textContent);
                addText(item, 'div', requirementSentence(row), 'small');
                if (value('[data-cert-notes]', row)) addText(item, 'div', `Notes: ${value('[data-cert-notes]', row)}`, 'small text-muted');
            };
            const list = document.createElement('ul');
            review.append(list);
            requirementRows($('[data-cert-ungrouped]', stage)).forEach((row) => addRequirement(list, row));
            rows('[data-cert-group]', stage).forEach((group) => {
                const item = addText(list, 'li', '', 'mb-3');
                addText(item, 'strong', groupSentence(group));
                const members = document.createElement('ul');
                item.append(members);
                requirementRows(group).forEach((row) => addRequirement(members, row));
            });
            if (!requirementRows(stage).length) addText(review, 'p', 'No requirements configured.');
        });
    }

    function resetRules(row) { rows('[data-cert-rule]', row).forEach(markDeleted); }

    root.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        const row = button.closest('[data-cert-requirement]');
        const stage = stageOf(button);
        if (button.dataset.certNav) navigate(button.dataset.certNav);
        if (button.dataset.certGoto) {
            navigate(button.dataset.certGoto);
            $('[name=program_scope_mode]:checked')?.focus();
        }
        if (button.hasAttribute('data-cert-edit')) openRequirement($('[data-cert-editor]', row).hidden ? row : null);
        if (button.hasAttribute('data-cert-done')) { openRequirement(null); $('[data-cert-edit]', row).focus(); }
        if (button.dataset.certAdd === 'requirement') newRow('requirement', stage, button.closest('[data-cert-group]')?.querySelector('[data-cert-members]') ?? $('[data-cert-ungrouped]', stage));
        if (button.dataset.certAdd === 'group') newRow('group', stage, $('[data-cert-groups]', stage));
        if (button.dataset.certAdd === 'role') newRow('role', null, $('[data-cert-role-rows]'));
        if (button.dataset.certAdd === 'rule') {
            const ruleRows = $$('[data-cert-rule]', row);
            const index = Math.max(-1, ...ruleRows.map((rule) => Number(rule.dataset.certRule))) + 1;
            const holder = document.createElement('template');
            holder.innerHTML = $('[data-cert-rule-template]', row).innerHTML.replaceAll('__RULE__', index);
            const rule = holder.content.firstElementChild;
            $('[data-cert-rules]', row).append(rule);
            sync();
            $('[data-cert-dimension]', rule).focus();
        }
        if (button.hasAttribute('data-cert-new-group')) {
            const group = newRow('group', stage, $('[data-cert-groups]', stage));
            row.dataset.pendingGroup = group.dataset.certGroup;
            sync();
        }
        if (button.hasAttribute('data-cert-manage-roles')) {
            roleOpener = button;
            const panel = $('[data-cert-roles]');
            $('[data-cert-editor]', row).append(panel);
            panel.hidden = false;
            (panel.querySelector('input:not([type=hidden]):not([disabled])') ?? $('[data-cert-add=role]', panel)).focus();
        }
        if (button.hasAttribute('data-cert-close-roles')) { $('[data-cert-roles]').hidden = true; roleOpener?.focus(); }
        if (button.dataset.certRemove) {
            const kind = button.dataset.certRemove;
            const target = kind === 'rule' ? button.closest('[data-cert-rule]') : kind === 'group' ? button.closest('[data-cert-group]') : kind === 'role' ? button.closest('[data-cert-role-row]') : row;
            if (kind === 'role') {
                const id = value('[data-cert-saved-role]', target) || `new:${target.dataset.certRoleRow}`;
                if (requirementRows().some((item) => value('[data-cert-role]', item) === id)) { alert('Choose another role on its requirements before removing this role.'); return; }
            }
            if (!confirm(kind === 'group' ? 'Ungroup these requirements? Each will become individually required.' : `Remove this ${kind}? This takes effect when you save the certificate.`)) return;
            if (kind === 'group') requirementRows(target).forEach((item) => { item.dataset.pendingGroup = ''; });
            markDeleted(target);
            sync();
            const focusTarget = kind === 'rule' ? $('[data-cert-add=rule]', row) : kind === 'role' ? $('[data-cert-add=role]', root) : $('[data-cert-add=requirement]', stage);
            focusTarget?.focus();
            announce(`${kind} removed. Save the certificate to keep changes.`);
        }
        if (button.dataset.certMove) {
            const target = button.closest('[data-cert-requirement], [data-cert-group]');
            const siblings = [...target.parentElement.children].filter((item) => item.matches('[data-cert-requirement], [data-cert-group]') && alive(item));
            const index = siblings.indexOf(target);
            const previous = siblings[index - 1], next = siblings[index + 1];
            if (button.dataset.certMove === 'up' && previous) previous.before(target);
            if (button.dataset.certMove === 'down' && next) next.after(target);
            sync();
            ($('[data-cert-edit]', target) ?? $('[data-cert-group-label]', target))?.focus();
            announce('Order updated.');
        }
        if (button.hasAttribute('data-cert-clear')) {
            const renewal = $('[data-cert-stage=renewal]');
            if (!confirm('Clear all independent renewal requirements and groups? This takes effect when you save.')) return;
            requirementRows(renewal).forEach(markDeleted);
            rows('[data-cert-group]', renewal).forEach(markDeleted);
            sync();
            $('[data-cert-add=requirement]', renewal).focus();
        }
        if (button.hasAttribute('data-cert-copy')) copyInitial();
    });

    function copyInitial() {
        const initial = $('[data-cert-stage=initial]'), renewal = $('[data-cert-stage=renewal]');
        const rolePanel = $('[data-cert-roles]');
        rolePanel.hidden = true;
        root.append(rolePanel);
        if ((requirementRows(renewal).length || rows('[data-cert-group]', renewal).length) && !confirm('Replace independent renewal rules with a copy of initial requirements? This takes effect when you save.')) return;
        requirementRows(renewal).forEach(markDeleted);
        rows('[data-cert-group]', renewal).forEach(markDeleted);
        const groupMap = new Map();
        const copy = (source, kind, destination) => {
            const clone = source.cloneNode(true);
            const sources = $$('input, select, textarea', source), targets = $$('input, select, textarea', clone);
            sources.forEach((control, index) => { targets[index].value = control.value; if (control.type === 'checkbox') targets[index].checked = control.checked; });
            // Replacing the requirement index also rewrites nested rule templates and all accessible IDs.
            const oldIndex = source.getAttribute(config[kind][1]);
            const newIndex = String(counters[kind]++);
            $$('input[name$="[id]"]', clone).forEach((input) => input.remove());
            $$('[data-cert-delete]', clone).filter((input) => input.value === '1').forEach((input) => input.parentElement.remove());
            if (kind === 'group') $('[data-cert-members]', clone).replaceChildren();
            const prefix = kind === 'group' ? 'requirement_groups' : 'requirements';
            $$('*', clone).concat(clone).forEach((element) => {
                [...element.attributes].forEach((attribute) => {
                    const changed = attribute.value.replaceAll(`${prefix}[${oldIndex}]`, `${prefix}[${newIndex}]`).replaceAll(`${prefix}.${oldIndex}.`, `${prefix}.${newIndex}.`).replaceAll(`${kind === 'group' ? 'group' : 'req'}-${oldIndex}-`, `${kind === 'group' ? 'group' : 'req'}-${newIndex}-`).replaceAll(`cert-${kind === 'group' ? 'group' : 'req'}-${oldIndex}`, `cert-${kind === 'group' ? 'group' : 'req'}-${newIndex}`).replaceAll(`cert-editor-${oldIndex}`, `cert-editor-${newIndex}`);
                    if (changed !== attribute.value) element.setAttribute(attribute.name, changed);
                });
            });
            clone.setAttribute(config[kind][1], newIndex);
            if (kind === 'requirement') {
                const template = $('[data-cert-rule-template]', clone);
                template.innerHTML = template.innerHTML.replaceAll(`requirements[${oldIndex}]`, `requirements[${newIndex}]`).replaceAll(`req-${oldIndex}-`, `req-${newIndex}-`).replaceAll(`requirements.${oldIndex}.`, `requirements.${newIndex}.`);
            }
            $$('[data-cert-phase]', clone).forEach((input) => { input.value = 'renewal'; });
            $$('[data-field-error]', clone).forEach((error) => error.remove());
            $$('[aria-invalid]', clone).forEach((input) => { input.removeAttribute('aria-invalid'); input.classList.remove('is-invalid'); });
            destination.append(clone);
            return clone;
        };
        rows('[data-cert-group]', initial).forEach((source) => {
            const clone = copy(source, 'group', $('[data-cert-groups]', renewal));
            groupMap.set(source.dataset.certGroup, clone.dataset.certGroup);
        });
        requirementRows(initial).forEach((source) => {
            const clone = copy(source, 'requirement', $('[data-cert-ungrouped]', renewal));
            clone.dataset.pendingGroup = groupMap.get(value('[data-cert-group-select]', source)) ?? '';
        });
        sync();
        openRequirement(null);
        announce('Initial rules copied. Renewal now has an independent copy.');
        $('[data-cert-add=requirement]', renewal).focus();
    }

    root.addEventListener('change', (event) => {
        const input = event.target;
        const row = input.closest('[data-cert-requirement]');
        if (input.matches('[data-cert-kind]')) {
            const previous = input.dataset.previous ?? '';
            if (previous && previous !== input.value) {
                const destructive = previous === 'tool_submission' || input.value === 'attestation';
                if (destructive && !confirm('Changing this type clears incompatible submission rules and filters. Keep the requirement name and notes?')) { input.value = previous; return; }
                if (previous === 'tool_submission') { resetRules(row); $('[data-cert-tool]', row).value = ''; }
                if (input.value === 'attestation') {
                    ['family', 'type', 'role', 'window'].forEach((field) => { $(`[data-cert-${field}]`, row).value = ''; });
                    $('[data-cert-target]', row).value = '1';
                }
            }
            input.dataset.previous = input.value;
        }
        if (input.matches('[data-cert-tool]')) {
            if (input.dataset.previous !== input.value && rows('[data-cert-rule]', row).length && !confirm('Changing tools clears the coverage and quota rules for the previous tool. Continue?')) { input.value = input.dataset.previous ?? ''; return; }
            if (input.dataset.previous !== input.value) resetRules(row);
            input.dataset.previous = input.value;
        }
        if (input.matches('[data-cert-dimension]')) {
            const rule = input.closest('[data-cert-rule]');
            if (input.dataset.previous !== input.value) {
                if ($$('[data-cert-options] input:checked', rule).length && !confirm('Changing dimensions clears the selected categories. Continue?')) { input.value = input.dataset.previous ?? ''; return; }
                $$('[data-cert-options] input', rule).forEach((option) => { option.checked = false; });
            }
            input.dataset.previous = input.value;
        }
        if (input.matches('[name=renewal_matches_initial][type=checkbox]') && input.checked) {
            const renewal = $('[data-cert-stage=renewal]');
            if ((requirementRows(renewal).length || rows('[data-cert-group]', renewal).length) && !confirm('Following initial requirements removes the independent renewal rules when you save. Continue?')) { input.checked = false; return; }
            requirementRows(renewal).forEach(markDeleted);
            rows('[data-cert-group]', renewal).forEach(markDeleted);
        }
        sync();
    });
    root.addEventListener('input', (event) => { if (event.target.matches('input, textarea')) sync(); });
    form.addEventListener('project-program-scope:change', sync);
    form.addEventListener('submit', () => sync());
    let handlingInvalid = false;
    form.addEventListener('invalid', (event) => {
        if (handlingInvalid) {
            event.preventDefault();
            return;
        }
        handlingInvalid = true;
        queueMicrotask(() => { handlingInvalid = false; });
        const section = event.target.closest('[data-cert-section]');
        if (section) navigate(section.dataset.certSection);
        const row = event.target.closest('[data-cert-requirement]');
        if (row) openRequirement(row);
    }, true);
    $$('[data-cert-kind], [data-cert-tool], [data-cert-dimension]').forEach((input) => { input.dataset.previous = input.value; });
    sync();
    const errors = JSON.parse(root.dataset.errors || '[]');
    const first = errors[0];
    if (first) {
        const match = first.match(/^requirements\.(\d+)\./);
        if (match) {
            const row = $(`[data-cert-requirement="${match[1]}"]`);
            if (row) { navigate(stageOf(row).dataset.certStage); openRequirement(row); }
        } else if (first.startsWith('requirement_groups.')) {
            const group = $(`[data-cert-group="${first.split('.')[1]}"]`);
            if (group) navigate(stageOf(group).dataset.certStage);
        } else if (first.startsWith('roles.')) {
            navigate('initial');
            $('[data-cert-roles]').hidden = false;
        }
        const error = $$('[data-field-error]').find((node) => node.getClientRects().length);
        error?.closest('[data-form-field]')?.querySelector('input:not([type=hidden]),select,textarea')?.focus();
    }
}

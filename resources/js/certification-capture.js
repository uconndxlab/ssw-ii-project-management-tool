function initCertificationCapture() {
    const root = document.querySelector('[data-certification-editor]');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const config = JSON.parse(root.querySelector('[data-certification-config]').textContent);
    const form = root.closest('form');
    const rowsRoot = root.querySelector('[data-certification-rows]');
    const picker = root.querySelector('#activity-certification-picker');
    const people = config.candidates;
    const tools = config.tools;
    const rows = Object.entries(config.rows || {}).map(([index, row]) => ({ ...row, key: index, submissions: Object.entries(row.submissions || {}).map(([key, sub]) => ({ ...sub, key })) }));
    let nextKey = Math.max(0, ...rows.map(row => Number(row.key) + 1));
    let open = null;
    let picking = false;
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const ids = name => [...form.querySelectorAll(`input[name="${name}[]"]`)].map(input => Number(input.value));
    const programIds = () => ids('program_ids');
    const agreementIds = () => ids('agreement_ids');
    const overlap = item => item.mode === 'all' || (item.mode === 'specific' && item.program_ids.some(id => programIds().includes(Number(id))));
    const coach = (tool, person) => programIds().some(id => config.coach_program_ids.includes(id)
        && (!tool || tool.mode === 'all' || (tool.mode === 'specific' && tool.program_ids.includes(id)))
        && (!person || person.enrollments.some(enrollment => enrollment.mode === 'all' || (enrollment.mode === 'specific' && enrollment.program_ids.includes(id)))));
    const assigned = person => person.active && person.enrollments.length > 0 && person.agreements.some(a => agreementIds().includes(Number(a.id)));
    const personFor = row => people.find(p => Number(p.id) === Number(row.certification_candidate_id));
    const matching = person => (person?.enrollments || []).filter(overlap).flatMap(enrollment => enrollment.requirements.filter(req => (!req.type_id || Number(req.type_id) === Number(form.querySelector('#activity_type_id')?.value)) && (!req.family_id || Number(req.family_id) === Number(form.querySelector('#contact_family_id')?.value))));
    const roles = row => [...new Map(matching(personFor(row)).filter(req => req.role_id).map(req => [String(req.role_id), { id: req.role_id, name: req.role_name }])).values()];
    const field = (label, name, control, required = false, help = '') => {
        const messages = config.errors[name.replaceAll('[', '.').replaceAll(']', '')] || [];
        return `<div class="mb-3" data-form-field${required ? ' data-field-required' : ''}><label for="${esc(name)}" class="form-label${required ? ' required-label' : ''}">${esc(label)}</label>${control}${help ? `<div class="form-text" id="${esc(name)}-help" data-field-help>${esc(help)}</div>` : ''}${messages.map((message, index) => `<div class="text-danger small" id="${esc(name)}-error-${index}" data-field-error role="alert">${esc(message)}</div>`).join('')}</div>`;
    };
    const hidden = (name, value) => `<input type="hidden" name="${esc(name)}" value="${esc(value)}">`;
    const text = (name, value) => `<textarea class="form-control" id="${esc(name)}" name="${esc(name)}" rows="2">${esc(value)}</textarea>`;
    const select = (name, options, value, prompt, required = false) => `<select class="form-select" id="${esc(name)}" name="${esc(name)}"${required ? ' required aria-required="true"' : ''}><option value="">${esc(prompt)}</option>${options.map(option => `<option value="${esc(option.id)}"${String(option.id) === String(value) ? ' selected' : ''}>${esc(option.name ?? option.label)}</option>`).join('')}</select>`;
    const progressUrl = (enrollment, row) => {
        const url = new URL(enrollment.url);
        if (row.agreement_id) url.searchParams.set('agreement_id', row.agreement_id);
        return url.toString();
    };
    const dirty = () => form.dispatchEvent(new Event('input', { bubbles: true }));
    const announce = message => { root.querySelector('[data-certification-status]').textContent = message; };
    function capture() {
        for (const row of rows) {
            const prefix = `certification_candidates[${row.key}]`;
            for (const key of ['certification_role_id', 'completion_units', 'notes']) {
                const input = form.elements.namedItem(`${prefix}[${key}]`);
                if (input) row[key] = input.value;
            }
            for (const sub of row.submissions) {
                if (sub.editable === false) continue;
                const name = `${prefix}[submissions][${sub.key}]`;
                if (!form.elements.namedItem(`${name}[certification_tool_id]`) || Number(sub._delete)) continue;
                for (const key of ['certification_tool_id', 'outcome', 'notes']) {
                    const input = form.elements.namedItem(`${name}[${key}]`);
                    if (input) sub[key] = input.value;
                }
                for (const key of ['dimensions', 'scores']) {
                    sub[key] = {};
                    [...form.elements].filter(input => input.name?.startsWith(`${name}[${key}]`)).forEach(input => {
                        const id = input.name.match(/\[(\d+)\]$/)?.[1];
                        if (id) sub[key][id] = input.value;
                    });
                }
            }
        }
    }
    function submission(row, sub) {
        const name = `certification_candidates[${row.key}][submissions][${sub.key}]`;
        const tool = tools.find(t => Number(t.id) === Number(sub.certification_tool_id));
        const editable = sub.editable !== false && coach(tool, personFor(row));
        const deleted = Boolean(Number(sub._delete));
        const outcome = { passed: 'Passed', not_passed: 'Did not pass', not_scored: 'Not scored' }[sub.outcome] || 'Choose outcome';
        const allowed = tools.filter(t => t.active && overlap(t) && coach(t, personFor(row))).sort((a, b) => {
            const matches = matching(personFor(row)).map(req => Number(req.tool_id));
            return Number(matches.includes(b.id)) - Number(matches.includes(a.id));
        });
        let html = `<div class="border rounded mt-2${deleted ? ' opacity-75' : ''}" data-submission="${esc(sub.key)}"${editable ? '' : ' data-submission-locked'}><div class="d-flex flex-wrap align-items-center gap-2 p-2 bg-light"><strong class="small${deleted ? ' text-decoration-line-through' : ''}">${esc(tool?.name || 'New submission')}</strong><span class="badge text-bg-${sub.outcome === 'passed' ? 'success' : 'secondary'}">${esc(outcome)}</span><span class="small text-muted">${(tool?.dimensions || []).map(dim => esc(dim.options.find(option => Number(option.id) === Number(sub.dimensions?.[dim.id]))?.label || '')).filter(Boolean).join(' · ')}</span>${editable && !deleted ? `<button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-sub-action="edit" aria-expanded="${row.openSubmission === sub.key}">${row.openSubmission === sub.key ? 'Done editing' : 'Edit submission'}</button>` : ''}${editable ? `<button type="button" class="btn btn-sm btn-outline-${deleted ? 'secondary' : 'danger'} ms-auto" data-sub-action="${deleted ? 'undo' : 'remove'}">${deleted ? 'Undo removal' : 'Remove submission'}</button>` : '<span class="small text-muted ms-auto">Coach in scope required to edit</span>'}</div>`;
        if (!editable) {
            return html + `<div class="p-3 small">${tool?.dimensions.map(dim => `${esc(dim.name)}: ${esc(dim.options.find(option => Number(option.id) === Number(sub.dimensions?.[dim.id]))?.label || '—')}`).join(' · ') || ''}<div>${tool?.score_fields.map(score => `${esc(score.name)}: ${esc(sub.scores?.[score.id] ?? '—')}`).join(' · ') || ''}</div><div>${esc(sub.notes)}</div></div></div>`;
        }
        if (deleted && !sub.id) return html + '</div>';
        html += hidden(`${name}[id]`, sub.id || '') + hidden(`${name}[_delete]`, deleted ? 1 : 0);
        if (deleted) return html + hidden(`${name}[certification_tool_id]`, sub.certification_tool_id) + '</div>';
        html += `<div class="p-3"${row.openSubmission === sub.key ? '' : ' hidden'}>`;
        html += sub.id ? hidden(`${name}[certification_tool_id]`, sub.certification_tool_id) : field('Tool', `${name}[certification_tool_id]`, select(`${name}[certification_tool_id]`, allowed, sub.certification_tool_id, 'Choose a tool…', true), true, 'Tools used by this candidate’s certificates appear first.');
        if (tool) {
            if (!tool.active || !overlap(tool)) html += '<p class="text-warning-emphasis small">This saved tool is unavailable for new submissions. Its evidence is preserved.</p>';
            if (tool.description) html += `<p class="small text-muted">${esc(tool.description)}</p>`;
            html += '<div class="row g-3">' + tool.dimensions.map(dim => `<div class="col-sm-6">${field(dim.name, `${name}[dimensions][${dim.id}]`, select(`${name}[dimensions][${dim.id}]`, dim.options, sub.dimensions?.[dim.id], `Choose ${dim.name.toLowerCase()}…`, true), true)}</div>`).join('') + '</div>';
            if (tool.score_fields.length) html += '<h4 class="h6">Recorded scores <span class="text-muted fw-normal small">· optional</span></h4><div class="row g-3">' + tool.score_fields.map(score => {
                const key = `${name}[scores][${score.id}]`;
                return `<div class="col-sm-6">${field(score.name, key, `<input type="number" step="any" min="-99999999" max="99999999" class="form-control" id="${esc(key)}" name="${esc(key)}" value="${esc(sub.scores?.[score.id])}">`, false, score.unit === 'percent' ? 'Percent' : score.unit === 'points' ? 'Points' : '')}</div>`;
            }).join('') + '</div>';
            const guidance = matching(personFor(row)).filter(req => Number(req.tool_id) === tool.id).map(req => req.guidance).filter(Boolean);
            if (guidance.length) html += `<p class="small bg-light border rounded p-2">Guidance: ${esc([...new Set(guidance)].join(' · '))}</p>`;
            html += field('Coach-judged outcome', `${name}[outcome]`, select(`${name}[outcome]`, [{id:'passed',name:'Passed'},{id:'not_passed',name:'Did not pass'},{id:'not_scored',name:'Not scored'}], sub.outcome, 'Choose outcome…', true), true, 'Scores are recorded only. The coach decides the outcome.');
            html += field('Submission notes', `${name}[notes]`, text(`${name}[notes]`, sub.notes));
        }
        return html + '</div></div>';
    }
    function renderRow(row) {
        const person = personFor(row);
        const name = `certification_candidates[${row.key}]`;
        const deleted = Boolean(Number(row._delete));
        const available = person?.active && person.agreements.some(a => agreementIds().includes(Number(a.id)));
        const roleOptions = roles(row);
        if (row.certification_role_id && !roleOptions.some(role => String(role.id) === String(row.certification_role_id))) roleOptions.push({ id: row.certification_role_id, name: `${row.role_name || 'Saved role'} (unavailable)` });
        const savedCount = Number(row.submission_count || 0);
        const count = row.submissions.length ? row.submissions.filter(sub => !Number(sub._delete)).length : savedCount;
        const removable = !savedCount || (row.submissions.length === savedCount && row.submissions.every(sub => sub.editable !== false && coach(tools.find(tool => Number(tool.id) === Number(sub.certification_tool_id)), personFor(row))));
        let html = `<div class="border rounded mb-2 bg-white${deleted ? ' opacity-75' : ''}" data-candidate="${esc(row.key)}">`;
        html += hidden(`${name}[id]`, row.id || '') + hidden(`${name}[certification_candidate_id]`, row.certification_candidate_id) + hidden(`${name}[agreement_id]`, row.agreement_id) + hidden(`${name}[_delete]`, deleted ? 1 : 0);
        html += `<div class="d-flex align-items-center flex-wrap gap-2 p-2"><strong class="${deleted ? 'text-decoration-line-through' : ''}">${esc(person?.name || 'Saved candidate')}</strong><span class="small text-muted">${esc(roleOptions.find(role => String(role.id) === String(row.certification_role_id))?.name || 'No role')} · ${esc(row.completion_units)} unit(s) · ${count} submission(s)</span><div class="d-flex gap-2 ms-auto">${!deleted ? `<button type="button" class="btn btn-sm btn-outline-secondary" data-candidate-action="edit" aria-expanded="${open === row.key}">${open === row.key ? 'Done editing' : 'Edit'}</button>` : ''}${removable ? `<button type="button" class="btn btn-sm btn-outline-${deleted ? 'secondary' : 'danger'}" data-candidate-action="${deleted ? 'undo' : 'remove'}">${deleted ? 'Undo removal' : 'Remove'}</button>` : ''}</div></div>`;
        if (deleted) return html + hidden(`${name}[completion_units]`, row.completion_units) + '</div>';
        html += `<div class="border-top p-3"${open === row.key ? '' : ' hidden'}>`;
        if (!available) html += '<p class="alert alert-warning small">This saved candidate is no longer available on a selected roster. The tag and existing evidence will be kept.</p>';
        html += '<div class="row g-3">';
        html += `<div class="col-sm-8">${roleOptions.length ? field('Activity role', `${name}[certification_role_id]`, select(`${name}[certification_role_id]`, roleOptions, row.certification_role_id, 'No role'), false, 'Roles come from this candidate’s certificate requirements for this activity type.') : hidden(`${name}[certification_role_id]`, row.certification_role_id || '') + '<p class="small text-muted">No role is required by this candidate’s certificates for this activity type.</p>'}</div>`;
        html += `<div class="col-sm-4">${field('Completion units', `${name}[completion_units]`, `<input type="number" min="1" max="999" required class="form-control" id="${esc(name)}[completion_units]" name="${esc(name)}[completion_units]" value="${esc(row.completion_units)}">`, true)}</div></div>`;
        html += field('Candidate notes', `${name}[notes]`, text(`${name}[notes]`, row.notes));
        html += '<div class="bg-light border rounded p-2 small"><strong>Where this can count</strong>';
        html += (person?.enrollments || []).map(enrollment => {
            let reasons;
            const applicable = enrollment.requirements.filter(req => req.kind !== 'attestation'
                && (!req.type_id || Number(req.type_id) === Number(form.querySelector('#activity_type_id')?.value))
                && (!req.family_id || Number(req.family_id) === Number(form.querySelector('#contact_family_id')?.value)));
            if (form.querySelector('[name="cancelled"][type="checkbox"]')?.checked) reasons = 'Not counted: activity is cancelled.';
            else if (form.querySelector('[name="not_yet_complete"][type="checkbox"]')?.checked) reasons = 'Work can count after the activity is complete.';
            else if (!overlap(enrollment)) reasons = 'Not counted: activity programs do not overlap.';
            else if (!applicable.length) reasons = 'No activity or tool requirements match this family and type.';
            else reasons = applicable.map(req => {
                const title = req.label || tools.find(tool => Number(tool.id) === Number(req.tool_id))?.name || 'Activity work';
                const role = req.role_id && Number(req.role_id) !== Number(row.certification_role_id) ? ` — choose ${req.role_name} role` : '';
                return `${title}${role}${req.tool_id ? ' — coach-recorded submission needed' : ''}`;
            }).join('; ') + '. Final credit depends on the requirement’s dates and conditions.';
            return `<div class="mt-1">${enrollment.url ? `<a href="${esc(progressUrl(enrollment, row))}">${esc(enrollment.name)}</a>` : esc(enrollment.name)} · ${esc(reasons)}</div>`;
        }).join('') || '<div>No certificates assigned. The activity tag is still recorded.</div>';
        html += '</div><div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mt-3"><h3 class="h6 mb-0">Tool submissions</h3>' + (coach(null, person) ? '<button type="button" class="btn btn-sm btn-outline-primary" data-candidate-action="add-submission">Add submission</button>' : '') + '</div>';
        if (!row.submissions.length) html += `<p class="small text-muted mt-2">${savedCount ? `${savedCount} recorded submission(s). Detailed evidence is limited to admins, coaches, and managers in scope.` : 'No submissions. Trainings can be tagged without recording a tool.'}</p>`;
        html += row.submissions.map(sub => submission(row, sub)).join('');
        if (!coach(null, person)) html += '<p class="small text-muted mt-2 mb-0">A certification coach records submissions. Turn on Needs certification review to flag this activity.</p>';
        return html + '</div></div>';
    }
    function render() {
        const active = document.activeElement;
        const focus = root.contains(active) ? { name: active.name, candidate: active.closest('[data-candidate]')?.dataset.candidate, submission: active.closest('[data-submission]')?.dataset.submission, candidateAction: active.dataset.candidateAction, subAction: active.dataset.subAction } : null;
        root.hidden = !config.review_requested && !rows.length && !people.some(assigned);
        rowsRoot.innerHTML = rows.map(renderRow).join('');
        const roleOptions = [...new Map(rows.filter(row => !Number(row._delete)).flatMap(roles).map(role => [role.id, role])).values()];
        const bulk = root.querySelector('[data-certification-bulk]');
        bulk.hidden = rows.length < 2 || !roleOptions.length;
        root.querySelector('[data-certification-bulk-role]').innerHTML = '<option value="">Choose role…</option>' + roleOptions.map(role => `<option value="${esc(role.id)}">${esc(role.name)}</option>`).join('');
        const flag = root.querySelector('#needs_certification_review');
        const wasFlagged = config.review_requested;
        const locked = wasFlagged && !coach();
        flag.disabled = locked;
        const off = root.querySelector('input[type="hidden"][name="needs_certification_review"]');
        off.disabled = locked;
        root.querySelector('[data-certification-review-help]').hidden = !locked;
        if (wasFlagged && locked) flag.checked = true;
        for (const input of rowsRoot.querySelectorAll('[required]')) {
            if (input.closest('[hidden]')) input.required = false;
        }
        if (focus) {
            let control;
            if (focus.name) control = form.elements.namedItem(focus.name);
            else if (focus.candidate !== undefined) {
                const candidate = [...rowsRoot.querySelectorAll('[data-candidate]')].find(el => el.dataset.candidate === focus.candidate);
                const container = focus.submission !== undefined ? [...(candidate?.querySelectorAll('[data-submission]') || [])].find(el => el.dataset.submission === focus.submission) : candidate;
                control = container?.querySelector(focus.subAction ? `[data-sub-action="${focus.subAction}"]` : `[data-candidate-action="${focus.candidateAction}"]`)
                    || container?.querySelector('button');
            }
            if (control instanceof HTMLElement && !control.closest('[hidden]')) control.focus({ preventScroll: true });
        }
        const activeError = Object.keys(config.errors || {}).find(key => key.startsWith('certification_candidates.'));
        if (activeError && !root.dataset.errorShown) {
            root.dataset.errorShown = 'true';
            open = activeError.split('.')[1];
            const row = rows.find(row => row.key === open);
            if (row && activeError.includes('.submissions.')) row.openSubmission = activeError.split('.')[3];
            render();
        }
    }
    function restrictPicker() {
        const available = people.filter(person => assigned(person) && !rows.some(row => Number(row.certification_candidate_id) === person.id && !Number(row._delete))).map(person => String(person.id));
        picker.dispatchEvent(new CustomEvent('token-picker:restrict', { detail: available }));
    }
    function scrollToCandidate(row) {
        requestAnimationFrame(() => {
            const target = rowsRoot.querySelector(`[data-candidate="${CSS.escape(row.key)}"]`);
            if (!target) return;
            const phoneBar = document.querySelector('.app-topbar');
            const phoneHeight = phoneBar?.offsetParent !== null ? phoneBar?.offsetHeight || 0 : 0;
            const headerHeight = document.querySelector('[data-header-sticky] .app-header-bar')?.offsetHeight || 0;
            const behavior = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth';
            window.scrollTo({ top: window.scrollY + target.getBoundingClientRect().top - phoneHeight - headerHeight - 16, behavior });
        });
    }
    root.addEventListener('click', async event => {
        const button = event.target.closest('button');
        if (!button) return;
        capture();
        if (button.matches('[data-certification-add]')) {
            root.querySelector('[data-certification-picker]').hidden = false;
            restrictPicker();
            picker.querySelector('input[type="text"]').focus();
            return;
        }
        if (button.matches('[data-certification-apply-role]')) {
            const roleId = root.querySelector('[data-certification-bulk-role]').value;
            let count = 0;
            rows.filter(row => !Number(row._delete)).forEach(row => {
                if (roles(row).some(role => String(role.id) === roleId)) { row.certification_role_id = roleId; count++; }
            });
            announce(`Role applied to ${count} candidates.`);
        }
        const rowEl = button.closest('[data-candidate]');
        const row = rows.find(item => item.key === rowEl?.dataset.candidate);
        const action = button.dataset.candidateAction;
        if (row && action) {
            if (action === 'edit') open = open === row.key ? null : row.key;
            if (action === 'remove') {
                if ((row.submissions.length || row.submission_count) && !await window.appConfirm({ title: 'Remove candidate?', record: personFor(row)?.name ?? '', message: 'The submissions recorded for this person will also be removed when you save.', action: 'Remove candidate', trigger: button })) return;
                row._delete = 1;
                announce('Candidate marked for removal. Undo is available until you save.');
            }
            if (action === 'undo') row._delete = 0;
            if (action === 'add-submission') {
                open = row.key;
                row.openSubmission = String(Math.max(0, ...row.submissions.map(sub => Number(sub.key) + 1)));
                row.submissions.push({ key: row.openSubmission, certification_tool_id: '', outcome: '', dimensions: {}, scores: {} });
            }
        }
        const subEl = button.closest('[data-submission]');
        if (row && subEl) {
            const sub = row.submissions.find(item => item.key === subEl.dataset.submission);
            if (button.dataset.subAction === 'edit') row.openSubmission = row.openSubmission === sub.key ? null : sub.key;
            if (button.dataset.subAction === 'remove') sub._delete = 1;
            if (button.dataset.subAction === 'undo') sub._delete = 0;
        }
        render();
        restrictPicker();
        if (action === 'edit' && open === row.key) scrollToCandidate(row);
        if (action === 'add-submission') rowsRoot.querySelector(`[data-candidate="${CSS.escape(row.key)}"] [data-submission="${CSS.escape(row.openSubmission)}"] select`)?.focus();
        if (action !== 'edit' && button.dataset.subAction !== 'edit') dirty();
    });
    picker.addEventListener('token-picker:change', () => {
        if (picking) return;
        const id = Number(picker.querySelector('input[type="hidden"]')?.value);
        const person = people.find(person => person.id === id);
        if (!person) return;
        picking = true;
        capture();
        const existing = rows.find(row => Number(row.certification_candidate_id) === id);
        if (existing) { existing._delete = 0; open = existing.key; }
        else {
            const agreement = person.agreements.find(a => agreementIds().includes(Number(a.id)));
            if (agreement) {
                const row = { key: String(nextKey++), certification_candidate_id: id, agreement_id: agreement.id, completion_units: 1, notes: '', submissions: [] };
                rows.push(row); open = row.key;
            }
        }
        picker.dispatchEvent(new CustomEvent('token-picker:set', { detail: [] }));
        picking = false;
        render(); restrictPicker(); dirty();
    });
    root.addEventListener('change', event => {
        if (event.target.name?.endsWith('[certification_tool_id]')) { capture(); render(); }
    });
    function scopeChanged() { capture(); render(); restrictPicker(); }
    form.addEventListener('project-program-scope:change', scopeChanged);
    form.querySelector('#activity-agreements-picker')?.addEventListener('token-picker:change', scopeChanged);
    form.addEventListener('change', event => { if (['activity_type_id', 'contact_family_id', 'cancelled', 'not_yet_complete'].includes(event.target.id)) scopeChanged(); });
    render();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initCertificationCapture);
else initCertificationCapture();

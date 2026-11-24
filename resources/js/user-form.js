document.addEventListener('DOMContentLoaded', function () {
    const root = document.querySelector('[data-privilege-ledger]');
    if (!root) return;

    const rows = root.querySelector('[data-privilege-rows]');
    const empty = root.querySelector('[data-privilege-empty]');
    const specific = root.querySelector('[data-specific-ledger]');
    const systemRow = root.querySelector('[data-system-access-row]');
    const preview = root.querySelector('[data-privilege-preview]');
    const projectPicker = document.getElementById('privilege-projects');
    const programPicker = document.getElementById('privilege-programs');
    const config = JSON.parse(root.querySelector('[data-privilege-ledger-config]').textContent);
    const { projectNames, programNames, projectBadgeClass, programBadgeClass } = config;
    let nextIndex = config.nextIndex;

    function coverage() {
        return root.querySelector('input[name="privilege_coverage"]:checked')?.value
            || root.querySelector('input[name="privilege_coverage"]')?.value
            || 'specific';
    }

    function pickerIds(picker) {
        if (!picker) return [];
        return Array.from(picker.querySelectorAll('[data-token-inputs] input[type="hidden"]')).map(function (input) {
            return String(input.value);
        });
    }

    function setPickerIds(picker, values) {
        if (!picker) return;
        picker.dispatchEvent(new CustomEvent('token-picker:set', {
            detail: values,
            bubbles: true,
        }));
    }

    function rowKey(type, id) {
        return String(type) + ':' + String(id);
    }

    function existingKeys() {
        const keys = new Set();
        rows.querySelectorAll('[data-privilege-row]').forEach(function (row) {
            keys.add(rowKey(row.dataset.scopeType, row.dataset.scopeId));
        });
        return keys;
    }

    function updatePreview() {
        if (!preview) return;
        if (coverage() === 'system') {
            const admin = root.querySelector('input[name="privilege_system_admin"][type="checkbox"]')?.checked;
            preview.textContent = admin
                ? 'Can edit and view all records system-wide.'
                : 'Can view all records system-wide; edit only activities they are on.';
            return;
        }
        const labels = [];
        rows.querySelectorAll('[data-privilege-row]').forEach(function (row) {
            const type = row.dataset.scopeType;
            const id = row.dataset.scopeId;
            const admin = row.querySelector('[name*="[admin]"][type="checkbox"]')?.checked;
            const name = type === 'project' ? projectNames[id] : programNames[id];
            labels.push((admin ? 'Admin of ' : 'Viewer of ') + (name || id));
        });
        preview.textContent = labels.length
            ? labels.join(' · ')
            : 'Add projects or programs to define this role\'s access.';
    }

    function updateEmpty() {
        const isSystem = coverage() === 'system';
        const hasRows = rows.querySelectorAll('[data-privilege-row]').length > 0;
        if (empty) empty.classList.toggle('d-none', isSystem || hasRows);
        updatePreview();
    }

    function syncCoverage() {
        const isSystem = coverage() === 'system';
        if (specific) specific.classList.toggle('d-none', isSystem);
        if (systemRow) systemRow.classList.toggle('d-none', !isSystem);
        rows.querySelectorAll('[data-privilege-row]').forEach(function (row) {
            row.classList.toggle('d-none', isSystem);
        });
        updateEmpty();
    }

    function addRow(type, id) {
        if (!id) return;
        const key = rowKey(type, id);
        if (existingKeys().has(key)) return;

        const name = type === 'project' ? projectNames[id] : programNames[id];
        const badgeClass = type === 'project' ? projectBadgeClass : programBadgeClass;
        const index = nextIndex++;
        const card = document.createElement('div');
        card.className = 'border rounded overflow-hidden bg-body';
        card.dataset.privilegeRow = '';
        card.dataset.scopeType = type;
        card.dataset.scopeId = String(id);
        card.innerHTML = '<div class="d-flex justify-content-between align-items-start gap-2 px-3 py-2">'
            + '<div class="min-w-0">'
            + '<span class="badge ' + badgeClass + '"></span>'
            + (type === 'project'
                ? '<div class="small text-muted mt-1">Permissions scoped to all programs within the project.</div>'
                : '')
            + '</div>'
            + '<div class="d-flex align-items-center gap-3">'
            + '<input type="hidden" name="privilege_entries[' + index + '][scope_type]" value="' + type + '">'
            + '<input type="hidden" name="privilege_entries[' + index + '][scope_id]" value="' + id + '">'
            + '<label class="form-check form-switch m-0 d-inline-flex align-items-center gap-2">'
            + '<input type="hidden" name="privilege_entries[' + index + '][admin]" value="0">'
            + '<input class="form-check-input mt-0" type="checkbox" name="privilege_entries[' + index + '][admin]" value="1" aria-label="Admin">'
            + '<span class="small text-muted">Admin</span>'
            + '</label>'
            + '<button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0 lh-1 fs-4" data-remove-privilege aria-label="Remove">&times;</button>'
            + '</div>'
            + '</div>';
        card.querySelector('.badge').textContent = name || id;
        rows.appendChild(card);
        updateEmpty();
    }

    function syncFromPickers() {
        const wanted = [];
        pickerIds(projectPicker).forEach(function (id) {
            wanted.push({ type: 'project', id: id });
        });
        pickerIds(programPicker).forEach(function (id) {
            wanted.push({ type: 'program', id: id });
        });
        const wantedKeys = new Set(wanted.map(function (item) {
            return rowKey(item.type, item.id);
        }));

        rows.querySelectorAll('[data-privilege-row]').forEach(function (row) {
            if (!wantedKeys.has(rowKey(row.dataset.scopeType, row.dataset.scopeId))) {
                row.remove();
            }
        });

        wanted.forEach(function (item) {
            addRow(item.type, item.id);
        });

        updateEmpty();
    }

    projectPicker?.addEventListener('token-picker:change', syncFromPickers);
    programPicker?.addEventListener('token-picker:change', syncFromPickers);

    rows.addEventListener('click', function (event) {
        const button = event.target.closest('[data-remove-privilege]');
        if (!button) return;
        const row = button.closest('[data-privilege-row]');
        if (!row) return;
        const type = row.dataset.scopeType;
        const id = String(row.dataset.scopeId);
        const picker = type === 'project' ? projectPicker : programPicker;
        setPickerIds(picker, pickerIds(picker).filter(function (value) {
            return value !== id;
        }));
    });
    rows.addEventListener('change', updatePreview);
    root.querySelector('input[name="privilege_system_admin"][type="checkbox"]')?.addEventListener('change', updatePreview);
    root.querySelectorAll('input[name="privilege_coverage"]').forEach(function (input) {
        input.addEventListener('change', syncCoverage);
    });

    syncCoverage();
});

document.addEventListener('DOMContentLoaded', function () {
    const cards = document.querySelectorAll('input[name="access_profile"]');
    const supervisor = document.querySelector('[data-supervisor-switch]');
    const panel = document.querySelector('[data-privilege-panel]');
    const supervisorInput = supervisor?.querySelector('input[type="checkbox"]');

    function sync() {
        const selected = document.querySelector('input[name="access_profile"]:checked')?.value;
        if (panel) panel.classList.toggle('d-none', selected !== 'admin_viewer');
        if (supervisor) supervisor.classList.toggle('d-none', selected === 'input');
        if (selected === 'input' && supervisorInput) supervisorInput.checked = false;
    }

    cards.forEach((input) => input.addEventListener('change', sync));
    sync();
});

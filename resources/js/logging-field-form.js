const fieldType = document.getElementById('field_type');
const wrapper = document.getElementById('options-field-wrapper');

if (fieldType && wrapper) {
    function sync() {
        const type = fieldType.value;
        const usesOptions = type === 'select' || type === 'multiselect';
        wrapper.classList.toggle('d-none', !usesOptions);
    }

    const active = document.getElementById('is_active');
    const warning = document.getElementById('logging-field-detach-warning');
    const form = warning.closest('form');
    const { attachmentGroups, savedScope, fieldName } = JSON.parse(form.querySelector('[data-logging-field-form-config]').textContent);
    const noun = (label, count) => count === 1 ? label : (label.endsWith('family') ? label.slice(0, -1) + 'ies' : label + 's');
    let removalConfirmed = false;

    function fieldFitsRecord(record, mode, programIds) {
        if (record.scope_mode === 'all') return mode !== 'none';
        if (record.scope_mode === 'none' || record.program_ids.length === 0) return false;
        if (mode === 'all') return true;
        if (mode === 'none' || programIds.length === 0) return false;
        return record.program_ids.some(id => programIds.includes(String(id)));
    }

    function syncAttachments() {
        removalConfirmed = false;
        const mode = form.querySelector('[name="program_scope_mode"]:checked')?.value ?? 'all';
        const programIds = Array.from(form.querySelectorAll('[name="program_ids[]"]')).map(input => input.value);
        const removing = [];
        document.querySelectorAll('[data-attachment-removing]').forEach(line => {
            const key = line.dataset.attachmentRemoving;
            const group = attachmentGroups[key];
            const wholeKind = !(active.checked && document.getElementById(key).checked);
            // Like the server: a scope change only removes records that fitted the saved scope.
            const affected = group.scope_records.filter(record => wholeKind
                || (fieldFitsRecord(record, savedScope.mode, savedScope.programIds) && !fieldFitsRecord(record, mode, programIds)));
            const isRemoving = affected.length > 0;
            const usage = affected.length === group.count
                ? `${affected.length} ${noun(group.label, affected.length)}`
                : `${affected.length} of ${group.count} ${noun(group.label, group.count)}`;
            line.querySelector('[data-removal-text]').textContent = `Will be removed from ${usage}`;
            line.hidden = !isRemoving;
            document.querySelector(`[data-attachment-used="${key}"]`).hidden = isRemoving;
            const records = document.querySelector(`[data-detach-records="${key}"]`);
            records.hidden = !isRemoving;
            const affectedIds = new Set(affected.map(record => String(record.id)));
            records.querySelectorAll('[data-attachment-record]').forEach(item => {
                item.hidden = !affectedIds.has(item.dataset.attachmentRecord);
            });
            const visibleIds = new Set(group.records.map(record => record.id));
            const hiddenCount = affected.filter(record => !visibleIds.has(record.id)).length;
            const hiddenLine = records.querySelector('[data-attachment-hidden]');
            hiddenLine.hidden = hiddenCount === 0;
            hiddenLine.textContent = `+${hiddenCount} ${noun(group.label, hiddenCount)} you don't have access to`;
            if (isRemoving) removing.push(usage);
        });
        warning.hidden = removing.length === 0;
        const list = removing.length > 1 ? `${removing.slice(0, -1).join(', ')} and ${removing.at(-1)}` : (removing[0] ?? '');
        warning.querySelector('[data-detach-summary]').textContent = `Saving removes this field from ${list}.`;
    }
    form.addEventListener('change', syncAttachments);
    form.addEventListener('project-program-scope:change', syncAttachments);
    syncAttachments();

    // The warning can sit off screen from the control that caused it, so confirm before a save that detaches.
    document.addEventListener('submit', async (event) => {
        if (event.target !== form || warning.hidden || removalConfirmed) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        removalConfirmed = await window.appConfirm({
            title: 'Remove this field from records?',
            record: fieldName,
            message: `${warning.querySelector('[data-detach-summary]').textContent} Answers already logged on activities are kept and stay editable.`,
            action: 'Save and remove',
            icon: 'bi-exclamation-triangle',
        });

        if (removalConfirmed) {
            form.requestSubmit();
        }
    }, true);

    fieldType.addEventListener('change', sync);
    sync();
}

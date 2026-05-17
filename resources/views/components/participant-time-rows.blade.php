<div class="participant-times-container">
    <div id="participant-times-list">
        {{-- Rows inserted by JS --}}
    </div>

    <button type="button" class="btn btn-sm btn-outline-secondary mt-3" id="add-participant-time-btn">
        <i class="bi bi-plus-lg"></i> Add Row Manually
    </button>
</div>

<template id="participant-time-row-template">
    <div class="participant-time-row card mb-2">
        <div class="card-body py-2 px-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-5">
                    <label class="form-label form-label-sm mb-1">Participant</label>
                    <select class="form-select form-select-sm participant-select" name="participant_times[__INDEX__][user_id]" required>
                        <option value="">Select team member&hellip;</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label form-label-sm mb-1">Hours</label>
                    <input type="number" class="form-control form-control-sm participant-hours"
                           name="participant_times[__INDEX__][hours]"
                           step="0.25" min="0.25" max="24" placeholder="1.5" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label form-label-sm mb-1">Notes <small class="text-muted fw-normal">(optional)</small></label>
                    <input type="text" class="form-control form-control-sm participant-notes"
                           name="participant_times[__INDEX__][notes]" placeholder="e.g. Attended remotely">
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn w-100" title="Remove">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
(function () {
    const container = document.getElementById('participant-times-list');
    const template = document.getElementById('participant-time-row-template');
    const addBtn = document.getElementById('add-participant-time-btn');

    if (!container || !template || !addBtn) return;

    let rowIndex = 0;

    function getParticipants() {
        return window.activityParticipants || {};
    }

    function populateSelect(select, preselectId) {
        const participants = getParticipants();
        const currentVal = preselectId !== undefined ? String(preselectId) : select.value;
        select.innerHTML = '<option value="">Select team member\u2026</option>';
        Object.entries(participants).forEach(function ([id, name]) {
            const opt = document.createElement('option');
            opt.value = id;
            opt.textContent = name;
            if (String(id) === currentVal) opt.selected = true;
            select.appendChild(opt);
        });
    }

    function refreshAllSelects() {
        container.querySelectorAll('.participant-select').forEach(function (s) { populateSelect(s); });
    }

    function getRowUserIds() {
        return Array.from(container.querySelectorAll('.participant-select'))
            .map(function (s) { return String(s.value); })
            .filter(Boolean);
    }

    function addRow(preselectId, hoursVal, notesVal) {
        const clone = template.content.cloneNode(true);
        const temp = document.createElement('div');
        temp.appendChild(clone);

        temp.querySelectorAll('[name*="__INDEX__"]').forEach(function (el) {
            el.name = el.name.replace('__INDEX__', rowIndex);
        });

        const row = temp.querySelector('.participant-time-row');
        if (!row) return null;

        container.appendChild(row);

        populateSelect(row.querySelector('.participant-select'), preselectId);
        if (hoursVal) row.querySelector('.participant-hours').value = hoursVal;
        if (notesVal) row.querySelector('.participant-notes').value = notesVal;

        row.querySelector('.remove-row-btn')?.addEventListener('click', function () { row.remove(); });

        rowIndex++;
        return row;
    }

    function syncFromSelected() {
        const mode = document.getElementById('time_tracking_mode_input')?.value;
        if (mode !== 'participant') return;

        const existingTimes = window.existingParticipantTimes || {};

        const checkedIds = Array.from(
            document.querySelectorAll('input[name="participant_user_ids[]"]:checked')
        ).map(function (cb) { return String(cb.value); });

        const rowIds = getRowUserIds();

        checkedIds.forEach(function (id) {
            if (!rowIds.includes(id)) {
                var saved = existingTimes[id];
                addRow(id, saved ? saved.hours : undefined, saved ? saved.notes : undefined);
            }
        });

        container.querySelectorAll('.participant-time-row').forEach(function (row) {
            var select = row.querySelector('.participant-select');
            var hours = row.querySelector('.participant-hours')?.value;
            if (select?.value && !checkedIds.includes(String(select.value)) && !hours) {
                row.remove();
            }
        });
    }

    addBtn.addEventListener('click', function (e) {
        e.preventDefault();
        addRow();
    });

    document.addEventListener('participants-updated', function () {
        refreshAllSelects();
        syncFromSelected();
    });

    document.addEventListener('change', function (e) {
        if (e.target?.name === 'participant_user_ids[]') {
            syncFromSelected();
        }
    });

    rowIndex = container.querySelectorAll('.participant-time-row').length;
    if (rowIndex > 0) refreshAllSelects();

    window.syncParticipantTimeRows = syncFromSelected;
})();
</script>

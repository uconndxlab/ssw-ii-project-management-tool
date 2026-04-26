<div class="participant-times-container">
    <div class="row g-3" id="participant-times-list">
        <!-- Rows will be inserted here -->
    </div>
    
    <button type="button" class="btn btn-sm btn-outline-secondary mt-3" id="add-participant-time-btn">
        <i class="bi bi-plus-lg"></i> Add Participant
    </button>
</div>

<template id="participant-time-row-template">
    <div class="col-12 participant-time-row">
        <div class="row g-2 align-items-end">
            <div class="col-md-6">
                <label class="form-label">Participant</label>
                <select class="form-select participant-select" name="participant_times[__INDEX__][user_id]" required>
                    <option value="">Select team member...</option>
                    {{-- Options populated by JS --}}
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Hours</label>
                <input type="number" class="form-control" name="participant_times[__INDEX__][hours]" 
                       step="0.25" min="0.25" max="24" placeholder="1.5" required>
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn" title="Remove this row">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
        <div class="mt-2">
            <small class="text-muted">
                <textarea class="form-control form-control-sm" name="participant_times[__INDEX__][notes]" 
                          rows="1" placeholder="Optional notes..." style="resize: vertical;"></textarea>
            </small>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('participant-times-list');
    const template = document.getElementById('participant-time-row-template');
    const addBtn = document.getElementById('add-participant-time-btn');
    let rowIndex = 0;

    // Get available participants from all selected agreements
    function getAvailableParticipants() {
        // This will be populated dynamically based on selected agreements
        // Format: { userId: 'name', ... }
        const agreementSelects = document.querySelectorAll('[data-agreement-participants]');
        const participants = {};
        
        agreementSelects.forEach(select => {
            const data = select.dataset.agreementParticipants;
            if (data) {
                try {
                    const parsed = JSON.parse(data);
                    Object.assign(participants, parsed);
                } catch (e) {
                    console.error('Failed to parse participant data:', e);
                }
            }
        });
        
        return participants;
    }

    function populateParticipantSelects() {
        const participants = getAvailableParticipants();
        const selects = container.querySelectorAll('.participant-select');
        
        selects.forEach(select => {
            const currentValue = select.value;
            select.innerHTML = '<option value="">Select team member...</option>';
            
            Object.keys(participants).forEach(userId => {
                const option = document.createElement('option');
                option.value = userId;
                option.textContent = participants[userId];
                if (userId == currentValue) {
                    option.selected = true;
                }
                select.appendChild(option);
            });
        });
    }

    function addRow() {
        const clone = template.content.cloneNode(true);
        const html = clone.innerHTML.replaceAll('__INDEX__', rowIndex);
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = html;
        const row = tempDiv.firstElementChild;
        
        container.appendChild(row);
        
        // Attach remove handler
        row.querySelector('.remove-row-btn').addEventListener('click', function () {
            row.remove();
        });
        
        rowIndex++;
        populateParticipantSelects();
    }

    // Add button handler
    addBtn.addEventListener('click', addRow);

    // Remove button handlers (event delegation)
    container.addEventListener('click', function (e) {
        if (e.target.closest('.remove-row-btn')) {
            e.preventDefault();
            e.target.closest('.participant-time-row').remove();
        }
    });

    // Initialize with existing rows if any
    const existingRows = container.querySelectorAll('.participant-time-row');
    if (existingRows.length > 0) {
        rowIndex = existingRows.length;
        populateParticipantSelects();
    }

    // Listen for agreement selection changes to update participant list
    document.addEventListener('agreement-selection-changed', populateParticipantSelects);
});
</script>

<style>
.participant-time-row {
    padding: 1rem;
    border: 1px solid var(--bs-border-color);
    border-radius: var(--bs-border-radius);
    background-color: var(--bs-body-bg);
}

.participant-time-row + .participant-time-row {
    margin-top: 0.75rem;
}

.participant-times-container {
    border-radius: var(--bs-border-radius);
}
</style>

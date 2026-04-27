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

    // Validate required elements exist
    if (!container || !template || !addBtn) {
        console.error('Participant time tracking: Required elements not found', {
            container: !!container,
            template: !!template,
            addBtn: !!addBtn
        });
        return;
    }

    // Get available participants from global variable
    function getAvailableParticipants() {
        // Populated by main activity form when agreements are selected
        return window.activityParticipants || {};
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
        if (!template) {
            console.error('Template not found');
            return;
        }

        const clone = template.content.cloneNode(true);
        
        // Create a temporary container to work with the cloned content
        const tempContainer = document.createElement('div');
        tempContainer.appendChild(clone);
        
        // Replace placeholders in all name attributes
        tempContainer.querySelectorAll('[name*="__INDEX__"]').forEach(element => {
            element.name = element.name.replace('__INDEX__', rowIndex);
        });
        
        // Get the row element
        const row = tempContainer.querySelector('.participant-time-row');
        
        if (!row) {
            console.error('Participant time row not found in template');
            return;
        }
        
        // Append to container
        container.appendChild(row);
        
        // Attach remove handler
        const removeBtn = row.querySelector('.remove-row-btn');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                row.remove();
            });
        }
        
        rowIndex++;
        populateParticipantSelects();
    }

    // Add button handler
    addBtn.addEventListener('click', function(e) {
        e.preventDefault();
        addRow();
    });

    // Remove button handlers (event delegation)
    container.addEventListener('click', function (e) {
        const removeBtn = e.target.closest('.remove-row-btn');
        if (removeBtn) {
            e.preventDefault();
            removeBtn.closest('.participant-time-row')?.remove();
        }
    });

    // Initialize with existing rows if any
    const existingRows = container.querySelectorAll('.participant-time-row');
    if (existingRows.length > 0) {
        rowIndex = existingRows.length;
        populateParticipantSelects();
    }

    // Listen for participants update from main form
    document.addEventListener('participants-updated', populateParticipantSelects);
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

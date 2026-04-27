@php
    // Determine available organizations and states based on selected agreements
    $availableOrganizations = collect();
    $availableStates = collect();
    $autoSelectOrg = null;
    $autoSelectState = null;
    
    if (!empty($agreementIds)) {
        $selectedAgreements = \App\Models\Agreement::with(['organizations', 'states'])
            ->whereIn('id', $agreementIds)
            ->get();
        
        // Get union of all organizations from selected agreements
        $availableOrganizations = $selectedAgreements
            ->flatMap(fn($agreement) => $agreement->organizations)
            ->unique('id')
            ->sortBy('name')
            ->values();
        
        // Get union of all states from selected agreements
        $availableStates = $selectedAgreements
            ->flatMap(fn($agreement) => $agreement->states)
            ->unique('id')
            ->sortBy('name')
            ->values();
        
        // Auto-select if only one option
        if ($availableOrganizations->count() === 1) {
            $autoSelectOrg = $availableOrganizations->first()->id;
        }
        
        if ($availableStates->count() === 1) {
            $autoSelectState = $availableStates->first()->id;
        }
    } else {
        // No agreements selected - show all (for internal activities)
        $availableOrganizations = \App\Models\Organization::orderBy('name')->get();
        $availableStates = \App\Models\State::orderBy('name')->get();
    }
    
    // Preserve user selections if they're still valid
    $selectedOrgIds = collect($selectedOrganizationIds ?? [])
        ->filter(fn($id) => $availableOrganizations->contains('id', $id))
        ->values()
        ->all();
    
    $selectedStateIds = collect($selectedStateIds ?? [])
        ->filter(fn($id) => $availableStates->contains('id', $id))
        ->values()
        ->all();
    
    // Apply auto-selection logic:
    // 1. If no user selections exist yet AND only one option available, auto-select it
    // 2. If user selections exist, add any single-option agreements to the selection
    if (empty($selectedOrgIds) && $autoSelectOrg) {
        $selectedOrgIds = [$autoSelectOrg];
    } elseif (!empty($agreementIds) && !empty($selectedAgreements)) {
        // For each agreement, if it has only 1 org/state, ensure it's selected
        foreach ($selectedAgreements as $agreement) {
            if ($agreement->organizations->count() === 1) {
                $orgId = $agreement->organizations->first()->id;
                if (!in_array($orgId, $selectedOrgIds)) {
                    $selectedOrgIds[] = $orgId;
                }
            }
        }
    }
    
    if (empty($selectedStateIds) && $autoSelectState) {
        $selectedStateIds = [$autoSelectState];
    } elseif (!empty($agreementIds) && !empty($selectedAgreements)) {
        // For each agreement, if it has only 1 state, ensure it's selected
        foreach ($selectedAgreements as $agreement) {
            if ($agreement->states->count() === 1) {
                $stateId = $agreement->states->first()->id;
                if (!in_array($stateId, $selectedStateIds)) {
                    $selectedStateIds[] = $stateId;
                }
            }
        }
    }
@endphp

<div class="col-md-6">
    <label class="form-label">
        Organizations
        @if(!empty($agreementIds))
            <span class="badge bg-secondary ms-1">{{ $availableOrganizations->count() }}</span>
        @endif
    </label>
    <x-token-picker
        picker-id="activity-organizations-picker"
        name="organization_ids[]"
        :items="$availableOrganizations"
        :selected-ids="$selectedOrgIds"
        :placeholder="!empty($agreementIds) ? 'Select organizations...' : 'Optional for internal activities'"
        :empty-message="!empty($agreementIds) ? 'No organizations linked to selected agreements.' : 'No organizations found.'"
    />
    @if(!empty($agreementIds) && $availableOrganizations->isEmpty())
        <small class="text-muted d-block mt-1">⚠️ Selected agreements have no linked organizations.</small>
    @endif
    @error('organization_ids')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

<div class="col-md-6">
    <label class="form-label">
        States
        @if(!empty($agreementIds))
            <span class="badge bg-secondary ms-1">{{ $availableStates->count() }}</span>
        @endif
    </label>
    <x-token-picker
        picker-id="activity-states-picker"
        name="state_ids[]"
        :items="$availableStates"
        :selected-ids="$selectedStateIds"
        :placeholder="!empty($agreementIds) ? 'Select states...' : 'Optional for internal activities'"
        :empty-message="!empty($agreementIds) ? 'No states linked to selected agreements.' : 'No states found.'"
    />
    @if(!empty($agreementIds) && $availableStates->isEmpty())
        <small class="text-muted d-block mt-1">⚠️ Selected agreements have no linked states.</small>
    @endif
    @error('state_ids')
        <div class="text-danger small mt-1">{{ $message }}</div>
    @enderror
</div>

{{-- Data attributes to pass selected IDs to parent script --}}
<div id="org-state-data" 
     data-org-ids='@json($selectedOrgIds)' 
     data-state-ids='@json($selectedStateIds)' 
     style="display:none;"></div>

@php
    $rowId = $row['id'] ?? null;
    $isExisting = filled($rowId);
    $candidateId = $row['certification_candidate_id'] ?? '';
    $name = $row['name'] ?? '';
    $email = $row['email'] ?? '';
    $person = $certificationCandidateOptions->firstWhere('id', (int) $candidateId);
    $originalName = $person?->name ?? $name;
    $originalEmail = $person?->email ?? '';
    $identityEditing = filled($candidateId) && ($name !== $originalName || $email !== $originalEmail);
    $prefix = "certification_candidates.$candidateIndex";
    $fieldId = 'cert-candidate-'.$candidateIndex;
    $deleted = filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $editing = ! $isExisting || $errors->has($prefix.'.*');
    $personMode = filled($candidateId) ? 'existing' : ((filled($name) || filled($email)) ? 'new' : 'search');
    $assignmentRows = $row['assignments'] ?? [];
    $nextAssignmentIndex = $assignmentRows === [] ? 0 : max(array_map('intval', array_keys($assignmentRows))) + 1;
    $assignedCount = collect($assignmentRows)->filter(fn ($a) => filled($a['certificate_id'] ?? null) && ! filter_var($a['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN))->count();
    $pickerOptions = $certificationCandidateOptions->where('active', true)->map(fn ($option) => [
        'id' => $option->id,
        'name' => $option->name,
        'search' => $option->name.' '.$option->email,
        'context' => $option->email,
    ]);
@endphp
<div class="repeater-row-card" data-repeater-row data-candidate-row data-repeater-collapsible
     id="certification-candidate-{{ $candidateId ?: 'new-'.$candidateIndex }}" data-person-mode="{{ $personMode }}"
     data-existing="{{ $isExisting ? '1' : '0' }}" data-repeater-mode="{{ $editing ? 'edit' : 'display' }}"
     data-original-name="{{ $originalName }}" data-original-email="{{ $originalEmail }}"
     data-identity-editing="{{ $identityEditing ? '1' : '0' }}"
     @if($deleted) style="display:none" @endif>
    @if($isExisting)
        <input type="hidden" name="certification_candidates[{{ $candidateIndex }}][id]" value="{{ $rowId }}">
    @endif
    <input type="hidden" name="certification_candidates[{{ $candidateIndex }}][_delete]" value="{{ $deleted ? '1' : '0' }}" data-repeater-delete>
    <input type="hidden" data-candidate-select name="certification_candidates[{{ $candidateIndex }}][certification_candidate_id]" value="{{ $candidateId }}">

    <div class="repeater-row-toolbar" data-row-display-click>
        <div class="repeater-row-summary">
            <span class="repeater-row-title" data-summary-value="name" data-summary-fallback="New candidate">{{ $name ?: 'New candidate' }}</span>
            <span class="repeater-row-meta d-block text-truncate mw-100" data-summary-value="email" data-summary-fallback="Email not recorded">{{ $email ?: 'Email not recorded' }}</span>
            <span class="repeater-row-meta" data-candidate-assignment-count>{{ $assignedCount }} {{ \Illuminate\Support\Str::plural('certificate', $assignedCount) }}</span>
            <span class="repeater-row-meta d-block text-truncate mw-100" data-candidate-certificate-summary hidden></span>
        </div>
        <div class="repeater-row-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-row-edit data-certification-edit
                    aria-label="{{ $editing ? 'Collapse' : 'Edit' }} candidate {{ $name }}"
                    aria-expanded="{{ $editing ? 'true' : 'false' }}" aria-controls="{{ $fieldId }}-body">{{ $editing ? 'Done' : 'Edit' }}</button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove
                    data-confirm-remove="Remove this person from this agreement? Their person record and certificate assignments will be kept."
                    aria-label="Remove {{ $name ?: 'candidate' }} from this agreement">Remove</button>
        </div>
    </div>

    <div class="repeater-row-body repeater-row-body--form" data-row-edit-fields id="{{ $fieldId }}-body">
        @if(! $isExisting)
            <div data-candidate-picker @if($personMode === 'new') hidden @endif>
                <x-form-field label="Candidate" :for="$fieldId.'-picker-search'" :name="$prefix.'.certification_candidate_id'">
                    <x-token-picker :picker-id="$fieldId.'-picker'" :name="'candidate_lookup['.$candidateIndex.']'"
                        :options="$pickerOptions" search-key="search" :single="true"
                        :selected-ids="filled($candidateId) ? [$candidateId] : []"
                        placeholder="Search name or email..." empty-message="No candidate found. Add a new person below."
                        height="220px" />
                </x-form-field>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary mb-3" data-new-candidate @if($personMode !== 'search') hidden @endif>Create a new candidate</button>
            <button type="button" class="btn btn-sm btn-link p-0 mb-3" data-back-to-search @if($personMode !== 'new') hidden @endif>Back to candidate search</button>
        @endif
        <div data-candidate-details @if($personMode === 'search') hidden @endif>
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <p class="form-text m-0" data-person-guidance>Enter a name and email for a new candidate.</p>
            <button type="button" class="btn btn-sm btn-link p-0" data-edit-identity hidden>Edit shared person details</button>
        </div>
        <div class="row g-3" data-person-details @if($personMode !== 'new' && ! $identityEditing) hidden @endif>
            <div class="col-md-6">
                <x-form-field label="Name" :for="$fieldId.'-name'" :name="$prefix.'.name'" class="mb-0">
                    <input type="text" class="form-control" id="{{ $fieldId }}-name" data-candidate-name data-summary-source="name"
                           name="certification_candidates[{{ $candidateIndex }}][name]" value="{{ $name }}" @readonly(filled($candidateId) && ! $identityEditing)>
                </x-form-field>
            </div>
            <div class="col-md-6">
                <x-form-field label="Email" :for="$fieldId.'-email'" :name="$prefix.'.email'" class="mb-0"
                              help="Required for new people and changes to shared person details.">
                    <input type="email" class="form-control" id="{{ $fieldId }}-email" data-candidate-email data-summary-source="email"
                           name="certification_candidates[{{ $candidateIndex }}][email]" value="{{ $email }}" @readonly(filled($candidateId) && ! $identityEditing)>
                </x-form-field>
            </div>
            <div class="col-12" data-email-match hidden>
                <div class="alert alert-info mb-0" role="status">
                    <span data-email-match-message></span>
                    <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-use-email-match>Use existing candidate</button>
                </div>
            </div>
        </div>
        <details class="mt-3" @if(filled($row['notes'] ?? null) || $errors->has($prefix.'.notes')) open @endif>
            <summary class="small text-muted">Agreement note (optional)</summary>
            <x-form-field label="Notes for this agreement" :for="$fieldId.'-notes'" :name="$prefix.'.notes'" class="mb-0 mt-2">
                <textarea class="form-control" rows="2" id="{{ $fieldId }}-notes"
                          name="certification_candidates[{{ $candidateIndex }}][notes]">{{ $row['notes'] ?? '' }}</textarea>
            </x-form-field>
        </details>
        <fieldset class="mt-4">
            <legend class="form-label float-none w-auto fs-6">Certificate assignments</legend>
            <x-repeater-rows name="certification_candidates[{{ $candidateIndex }}][assignments]"
                :rows="$assignmentRows" :next-index="$nextAssignmentIndex" index-token="__ASSIGN_INDEX__"
                add-label="Add certificate" variant="nested" :sortable="false" :scroll="false"
                :template="view('agreements.partials.certification-assignment-row', [
                    'row' => [], 'candidateIndex' => $candidateIndex, 'assignmentIndex' => '__ASSIGN_INDEX__',
                    'eligibleCertificates' => $eligibleCertificates, 'eligibleCoaches' => $eligibleCoaches,
                ])->render()">
                @foreach($assignmentRows as $assignmentIndex => $assignmentRow)
                    @include('agreements.partials.certification-assignment-row', [
                        'row' => $assignmentRow, 'candidateIndex' => $candidateIndex, 'assignmentIndex' => $assignmentIndex,
                    ])
                @endforeach
            </x-repeater-rows>
        </fieldset>
        </div>
    </div>
</div>

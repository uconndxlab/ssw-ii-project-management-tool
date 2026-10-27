@php
    $certificateId = $row['certificate_id'] ?? null;
    $isExisting = filled($row['enrollment_id'] ?? null);
    $canManage = ! $isExisting || ($row['can_manage'] ?? false);
    $canRemove = ! $isExisting || ($row['can_remove'] ?? false);
    $certificate = $eligibleCertificates->firstWhere('id', (int) $certificateId);
    $certificateName = $row['certificate_name'] ?? $certificate?->name ?? 'Select a certificate';
    $prefix = "certification_candidates.$candidateIndex.assignments.$assignmentIndex";
    $fieldId = 'cert-assignment-'.$candidateIndex.'-'.$assignmentIndex;
    $deleted = filter_var($row['_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $originalCoachId = array_key_exists('original_coach_user_id', $row) ? $row['original_coach_user_id'] : ($row['coach_user_id'] ?? '');
@endphp
<div class="py-3 border-bottom" data-repeater-row data-assignment-row
     data-existing="{{ $isExisting ? '1' : '0' }}" data-certificate-name="{{ $certificateName }}"
     data-certificate-scope="{{ $row['certificate_scope'] ?? $certificate?->program_scope_mode->value }}"
     data-certificate-programs='@json($row["certificate_program_ids"] ?? $certificate?->programs->modelKeys() ?? [])'
     @if($deleted) style="display:none" @endif>
    <input type="hidden" name="certification_candidates[{{ $candidateIndex }}][assignments][{{ $assignmentIndex }}][_delete]"
           value="{{ $deleted ? '1' : '0' }}" data-repeater-delete>
    @if($isExisting)
        <input type="hidden" name="certification_candidates[{{ $candidateIndex }}][assignments][{{ $assignmentIndex }}][enrollment_id]" value="{{ $row['enrollment_id'] }}">
        <input type="hidden" name="certification_candidates[{{ $candidateIndex }}][assignments][{{ $assignmentIndex }}][certificate_id]" value="{{ $certificateId }}">
    @endif
    <div class="d-flex justify-content-between align-items-start gap-3 mb-2">
        <strong data-certificate-title>{{ $isExisting ? $certificateName : 'Certificate assignment' }}</strong>
        @if($canRemove)
            <button type="button" class="btn btn-sm btn-outline-danger" data-repeater-remove
                    data-confirm-remove-title="Remove certificate assignment?"
                    data-confirm-remove="Remove this certificate assignment for this person everywhere it is displayed? Removing the person from this agreement is a separate action."
                    aria-label="Remove assignment {{ $certificateName }}">Remove</button>
        @endif
    </div>
    @if(! $isExisting)
        <div data-certificate-field>
        <x-form-field label="Certificate" :for="$fieldId.'-certificate'" :name="$prefix.'.certificate_id'">
            <select class="form-select" id="{{ $fieldId }}-certificate" data-certificate-select
                    name="certification_candidates[{{ $candidateIndex }}][assignments][{{ $assignmentIndex }}][certificate_id]">
                <option value="">Select a certificate…</option>
                @foreach($eligibleCertificates as $option)
                    <option value="{{ $option->id }}" data-program-scope="{{ $option->program_scope_mode->value }}"
                            data-program-ids='@json($option->programs->modelKeys())'
                            @selected((string) $certificateId === (string) $option->id)>{{ $option->name }}</option>
                @endforeach
            </select>
        </x-form-field>
        </div>
    @endif
    @if($canManage)
        <div data-coach-field>
        <x-form-field label="Supervising coach" :for="$fieldId.'-coach'" :name="$prefix.'.coach_user_id'" class="mb-0"
                      :help="$isExisting ? 'Changing the coach updates this person’s shared assignment.' : 'Optional. A coach needs a matching duty and access to this agreement.'">
            <select class="form-select" id="{{ $fieldId }}-coach" data-coach-select
                    data-original-coach="{{ $originalCoachId }}"
                    name="certification_candidates[{{ $candidateIndex }}][assignments][{{ $assignmentIndex }}][coach_user_id]">
                <option value="">No coach</option>
                @if(filled($row['coach_user_id'] ?? null) && ! $eligibleCoaches->contains('id', (int) $row['coach_user_id']))
                    <option value="{{ $row['coach_user_id'] }}" selected data-preserved-coach>{{ $row['coach_name'] ?? 'Current coach' }} (currently unavailable)</option>
                @endif
                @foreach($eligibleCoaches as $coach)
                    <option value="{{ $coach->id }}" data-duty-programs='@json($coach->certification()->coachProgramIds())'
                            data-view-programs='@json($coach->access()->viewProgramIds())'
                            data-can-view-agreement="{{ $coach->getAttribute('can_view_assignment_agreement') ? '1' : '0' }}"
                            @selected((string) ($row['coach_user_id'] ?? '') === (string) $coach->id)>{{ $coach->name }}</option>
                @endforeach
            </select>
        </x-form-field>
        </div>
    @else
        <p class="form-text mb-0">Coach: {{ $row['coach_name'] ?? 'No coach' }}. This shared assignment cannot be changed here.</p>
    @endif
</div>

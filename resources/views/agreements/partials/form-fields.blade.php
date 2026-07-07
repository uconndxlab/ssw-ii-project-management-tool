@php
    $agreement = $agreement ?? null;
    $agreementLoggingFieldCollection = $agreement?->agreementLoggingFields ?? collect();

    $selectedOrganizationIds = old('organization_ids', $agreement?->organizations?->pluck('id')->toArray() ?? []);
    $selectedStateIds = old('state_ids', $agreement?->states?->pluck('id')->toArray() ?? []);
    $selectedAgreementLoggingFieldIds = old('agreement_logging_field_ids', $agreementLoggingFieldCollection->pluck('id')->toArray());
    $requiredAgreementLoggingFieldIds = old(
        'required_agreement_logging_field_ids',
        $agreementLoggingFieldCollection->filter(fn ($field) => $field->pivot->is_required)->pluck('id')->toArray()
    );

    $rawCertificationCandidateRows = old('certification_candidates');
    $certificationCandidateRows = [];

    if (is_array($rawCertificationCandidateRows)) {
        foreach ($rawCertificationCandidateRows as $key => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowKey = is_string($key) ? $key : 'row-' . $key;
            $certificationCandidateRows[] = [
                'row_key' => $rowKey,
                'id' => $row['id'] ?? '',
                'value' => $row['value'] ?? '',
                '_delete' => !empty($row['_delete']) ? 1 : 0,
            ];
        }
    } elseif ($agreement?->certificationCandidates) {
        foreach ($agreement->certificationCandidates as $candidate) {
            $certificationCandidateRows[] = [
                'row_key' => 'existing-' . $candidate->id,
                'id' => $candidate->id,
                'value' => $candidate->name,
                '_delete' => 0,
            ];
        }
    }
@endphp

<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3">Agreement Information</h5>

        <div class="mb-3">
            <label for="name" class="form-label">Agreement Name</label>
            <input type="text"
                   class="form-control @error('name') is-invalid @enderror"
                   id="name"
                   name="name"
                   value="{{ old('name', $agreement?->name ?? '') }}"
                   required>
            @error('name')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Organizations</label>
                    <x-token-picker
                        picker-id="agreement-organizations"
                        name="organization_ids[]"
                        :items="$organizations"
                        :selected-ids="$selectedOrganizationIds"
                        placeholder="Search organizations..."
                        :height="'300px'"
                    />
                    @error('organization_ids')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">States</label>
                    <x-token-picker
                        picker-id="agreement-states"
                        name="state_ids[]"
                        :items="$states"
                        :selected-ids="$selectedStateIds"
                        placeholder="Search states..."
                        :height="'300px'"
                    />
                    @error('state_ids')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="mb-3">
            <label for="abstract" class="form-label">Abstract</label>
            <textarea class="form-control @error('abstract') is-invalid @enderror"
                      id="abstract"
                      name="abstract"
                      rows="4">{{ old('abstract', $agreement?->abstract ?? '') }}</textarea>
            @error('abstract')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date"
                           class="form-control @error('start_date') is-invalid @enderror"
                           id="start_date"
                           name="start_date"
                           value="{{ old('start_date', $agreement?->start_date?->format('Y-m-d')) }}">
                    @error('start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date"
                           class="form-control @error('end_date') is-invalid @enderror"
                           id="end_date"
                           name="end_date"
                           value="{{ old('end_date', $agreement?->end_date?->format('Y-m-d')) }}">
                    @error('end_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label for="extension_start_date" class="form-label">Extension Start Date</label>
                    <input type="date"
                           class="form-control @error('extension_start_date') is-invalid @enderror"
                           id="extension_start_date"
                           name="extension_start_date"
                           value="{{ old('extension_start_date', $agreement?->extension_start_date?->format('Y-m-d')) }}">
                    @error('extension_start_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="col-md-6">
                <div class="mb-3">
                    <label for="extension_end_date" class="form-label">Extension End Date</label>
                    <input type="date"
                           class="form-control @error('extension_end_date') is-invalid @enderror"
                           id="extension_end_date"
                           name="extension_end_date"
                           value="{{ old('extension_end_date', $agreement?->extension_end_date?->format('Y-m-d')) }}">
                    @error('extension_end_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-3">
            <x-inline-string-list
                list-id="agreement-certification-candidates"
                name="certification_candidates"
                label="Certification Candidates"
                :rows="$certificationCandidateRows"
                :suggestions="$candidateNameSuggestions ?? []"
                add-button-text="Add Candidate"
                empty-message="No certification candidates added yet."
                input-placeholder="Type a candidate name..."
            />
        </div>
    </div>
</div>

@include('agreements.partials.attachments-section', ['agreement' => $agreement])

<div class="card mb-4">
    <div class="card-body">
        <h5 class="mb-3">Logging Fields</h5>

        <div class="mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="mb-1">Agreement-Specific Logging Fields</h6>
                    <p class="text-muted small mb-0">These fields will be emphasized when activity is logged against this agreement.</p>
                </div>
                <a href="{{ route('logging-fields.index') }}" class="btn btn-sm btn-outline-secondary">Manage Logging Fields</a>
            </div>

            @if($agreementLoggingFields->isEmpty())
                <div class="alert alert-light border mb-0">No agreement logging fields have been defined yet.</div>
            @else
                <div class="border rounded">
                    @foreach($agreementLoggingFields as $field)
                        <label class="d-flex align-items-start gap-3 px-3 py-2 border-bottom {{ $loop->last ? 'border-bottom-0' : '' }}">
                            <input class="form-check-input mt-1"
                                   type="checkbox"
                                   name="agreement_logging_field_ids[]"
                                   value="{{ $field->id }}"
                                   {{ in_array($field->id, $selectedAgreementLoggingFieldIds) ? 'checked' : '' }}>
                            <div class="flex-grow-1">
                                <div class="fw-semibold">{{ $field->name }}</div>
                                <div class="small text-muted">{{ ucfirst($field->field_type) }}{{ $field->help_text ? ' · ' . $field->help_text : '' }}</div>
                            </div>
                            <div class="form-check m-0">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="required_agreement_logging_field_ids[]"
                                       value="{{ $field->id }}"
                                       {{ in_array($field->id, $requiredAgreementLoggingFieldIds) ? 'checked' : '' }}>
                                <label class="form-check-label small">Required</label>
                            </div>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>

@include('agreements.partials.membership-section', [
    'agreement' => $agreement,
    'teams' => $teams,
    'users' => $users,
])

@include('agreements.partials.deliverables-section', ['agreement' => $agreement])

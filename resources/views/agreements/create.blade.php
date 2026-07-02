@extends('layouts.app')

@section('title', 'Create Agreement')

@section('content')
@php
    $selectedAgreementLoggingFieldIds = old('agreement_logging_field_ids', []);
    $requiredAgreementLoggingFieldIds = old('required_agreement_logging_field_ids', []);
    $agreementUserOptions = $users->map(function ($user) {
        $role = !empty($user->role) ? ' (' . ucfirst($user->role) . ')' : '';

        return [
            'value' => $user->id,
            'label' => $user->name . $role,
            'search' => trim($user->name . ' ' . ($user->email ?? '') . ' ' . ($user->role ?? '')),
        ];
    });
@endphp
<div class="row justify-content-center mb-4">
    <div class="col-md-8">
        <h1>Create Agreement</h1>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('agreements.store') }}" id="agreements-create-form" enctype="multipart/form-data">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Agreement Name</label>
                        <input type="text"
                               class="form-control @error('name') is-invalid @enderror"
                               id="name"
                               name="name"
                               value="{{ old('name') }}"
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
                                    :selected-ids="old('organization_ids', [])"
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
                                    :selected-ids="old('state_ids', [])"
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
                                  rows="4">{{ old('abstract') }}</textarea>
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
                                       value="{{ old('start_date') }}">
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
                                       value="{{ old('end_date') }}">
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="original_end_date" class="form-label">Original End Date</label>
                                <input type="date"
                                       class="form-control @error('original_end_date') is-invalid @enderror"
                                       id="original_end_date"
                                       name="original_end_date"
                                       value="{{ old('original_end_date') }}">
                                @error('original_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="extended_end_date" class="form-label">Extended End Date</label>
                                <input type="date"
                                       class="form-control @error('extended_end_date') is-invalid @enderror"
                                       id="extended_end_date"
                                       name="extended_end_date"
                                       value="{{ old('extended_end_date') }}">
                                @error('extended_end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="certification_candidates" class="form-label">Certification Candidates</label>
                        <textarea class="form-control @error('certification_candidates') is-invalid @enderror"
                                  id="certification_candidates"
                                  name="certification_candidates"
                                  rows="3">{{ old('certification_candidates') }}</textarea>
                        @error('certification_candidates')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1">Agreement-Specific Logging Fields</h5>
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

                    <div class="mb-3">
                        <label class="form-label">Assign Users</label>

                        <x-token-picker
                            picker-id="agreement-create-users"
                            name="user_ids[]"
                            :options="$agreementUserOptions"
                            :selected-ids="old('user_ids', [])"
                            label-key="label"
                            value-key="value"
                            search-key="search"
                            placeholder="Search to assign users..."
                            :height="'300px'"
                        />
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Assign Teams</label>

                        <x-team-picker
                            picker-id="agreement-create-teams"
                            name="team_ids[]"
                            :teams="$teams"
                            :selected-ids="old('team_ids', [])"
                            search-placeholder="Search to assign teams..."
                        />

                        <small class="text-muted">
                            All users in assigned teams will have access to this agreement.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Attachments</label>
                        <input type="file"
                               class="form-control @error('attachments.*') is-invalid @enderror"
                               name="attachments[]"
                               multiple
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.txt">
                        <div class="form-text">PDF, Word, Excel, or text files. Max 10MB each.</div>
                        @error('attachments.*')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="agreements-create-form" cancel-url="{{ route('agreements.index') }}" save-label="Create Agreement" />
@endsection

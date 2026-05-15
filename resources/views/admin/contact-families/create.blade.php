@extends('layouts.app')

@section('title', 'Create Contact Family')

@section('content')
@php
    $selectedContactFamilyLoggingFieldIds = old('contact_family_logging_field_ids', []);
    $requiredContactFamilyLoggingFieldIds = old('required_contact_family_logging_field_ids', []);
@endphp
<div class="row mb-4">
    <div class="col-12">
        <h1>Create Contact Family</h1>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
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

                <form method="POST" action="{{ route('contact-families.store') }}" id="contact-families-create-form">
                    @csrf

                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
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

                    <div class="mb-3">
                        <label for="sort_order" class="form-label">Sort Order</label>
                        <input type="number" 
                               class="form-control @error('sort_order') is-invalid @enderror" 
                               id="sort_order" 
                               name="sort_order" 
                               value="{{ old('sort_order', 0) }}" 
                               min="0">
                        @error('sort_order')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="form-text">Used to order contact families in dropdowns. Lower numbers appear first.</div>
                    </div>

                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" 
                                   class="form-check-input" 
                                   id="active" 
                                   name="active" 
                                   value="1" 
                                   {{ old('active', true) ? 'checked' : '' }}>
                            <label class="form-check-label" for="active">
                                Active
                            </label>
                        </div>
                        <div class="form-text">Only active contact families appear in activity forms.</div>
                    </div>

                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h5 class="mb-1">Classification Logging Fields</h5>
                                <p class="text-muted small mb-0">These fields appear in the activity classification area when this contact family is selected.</p>
                            </div>
                            <a href="{{ route('contact-family-logging-fields.index') }}" class="btn btn-sm btn-outline-secondary">Manage Contact Family Fields</a>
                        </div>

                        @if($contactFamilyLoggingFields->isEmpty())
                            <div class="alert alert-light border mb-0">No contact family logging fields have been defined yet.</div>
                        @else
                            <div class="border rounded">
                                @foreach($contactFamilyLoggingFields as $field)
                                    <label class="d-flex align-items-start gap-3 px-3 py-2 border-bottom {{ $loop->last ? 'border-bottom-0' : '' }}">
                                        <input class="form-check-input mt-1"
                                               type="checkbox"
                                               name="contact_family_logging_field_ids[]"
                                               value="{{ $field->id }}"
                                               {{ in_array($field->id, $selectedContactFamilyLoggingFieldIds) ? 'checked' : '' }}>
                                        <div class="flex-grow-1">
                                            <div class="fw-semibold">{{ $field->name }}</div>
                                            <div class="small text-muted">{{ ucfirst($field->field_type) }}{{ $field->help_text ? ' · ' . $field->help_text : '' }}</div>
                                        </div>
                                        <div class="form-check m-0">
                                            <input class="form-check-input"
                                                   type="checkbox"
                                                   name="required_contact_family_logging_field_ids[]"
                                                   value="{{ $field->id }}"
                                                   {{ in_array($field->id, $requiredContactFamilyLoggingFieldIds) ? 'checked' : '' }}>
                                            <label class="form-check-label small">Required</label>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="contact-families-create-form" cancel-url="{{ route('contact-families.index') }}" save-label="Create Contact Family" />
@endsection

@extends('layouts.app')

@section('title', 'Edit Contact Family')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Edit Contact Family</h1>
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

                <form method="POST" action="{{ route('contact-families.update', $contactFamily) }}" id="contact-families-edit-form">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label for="name" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" 
                               class="form-control @error('name') is-invalid @enderror" 
                               id="name" 
                               name="name" 
                               value="{{ old('name', $contactFamily->name) }}" 
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
                               value="{{ old('sort_order', $contactFamily->sort_order) }}" 
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
                                   {{ old('active', $contactFamily->active) ? 'checked' : '' }}>
                            <label class="form-check-label" for="active">
                                Active
                            </label>
                        </div>
                        <div class="form-text">Only active contact families appear in activity forms.</div>
                    </div>

                    <hr class="my-4">

                    @php
                        $selectedLoggingFieldIds = old('logging_field_ids', $contactFamily->loggingFields->pluck('id')->toArray());
                        $requiredLoggingFieldIds = old('required_logging_field_ids', 
                            $contactFamily->loggingFields->filter(fn($field) => $field->pivot->is_required)->pluck('id')->toArray()
                        );
                    @endphp

                    <h5 class="mb-3">Activity Logging Fields</h5>
                    <p class="text-muted small mb-3">✅ Select fields that should be available when logging activities for this contact family:</p>
                    
                    @foreach($loggingFields as $field)
                        <div class="border-bottom pb-2 mb-2">
                            <div class="form-check">
                                <input class="form-check-input logging-field-checkbox"
                                       type="checkbox"
                                       name="logging_field_ids[]"
                                       id="logging_field_{{ $field->id }}"
                                       value="{{ $field->id }}"
                                       {{ in_array($field->id, $selectedLoggingFieldIds) ? 'checked' : '' }}
                                       onchange="toggleRequiredCheckbox({{ $field->id }})">
                                <label class="form-check-label fw-semibold" for="logging_field_{{ $field->id }}">
                                    {{ $field->name }}
                                    <span class="badge bg-secondary text-uppercase small">{{ $field->field_type }}</span>
                                </label>
                            </div>
                            @if($field->help_text)
                                <small class="text-muted d-block ms-4">{{ $field->help_text }}</small>
                            @endif
                            <div class="form-check ms-4 mt-1" id="required_{{ $field->id }}" style="display: {{ in_array($field->id, $selectedLoggingFieldIds) ? 'block' : 'none' }};">
                                <input class="form-check-input"
                                       type="checkbox"
                                       name="required_logging_field_ids[]"
                                       id="required_logging_field_{{ $field->id }}"
                                       value="{{ $field->id }}"
                                       {{ in_array($field->id, $requiredLoggingFieldIds) ? 'checked' : '' }}>
                                <label class="form-check-label small text-muted" for="required_logging_field_{{ $field->id }}">
                                    Make this field required
                                </label>
                            </div>
                        </div>
                    @endforeach

                    <script>
                    function toggleRequiredCheckbox(fieldId) {
                        const checkbox = document.getElementById('logging_field_' + fieldId);
                        const requiredDiv = document.getElementById('required_' + fieldId);
                        const requiredCheckbox = document.getElementById('required_logging_field_' + fieldId);
                        
                        if (checkbox.checked) {
                            requiredDiv.style.display = 'block';
                        } else {
                            requiredDiv.style.display = 'none';
                            requiredCheckbox.checked = false;
                        }
                    }
                    </script>

                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="contact-families-edit-form" cancel-url="{{ route('contact-families.index') }}" save-label="Save Contact Family" :last-saved-at="$contactFamily->updated_at" />
@endsection

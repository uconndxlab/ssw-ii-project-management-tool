@extends('layouts.app')

@section('title', $contactFamilyLoggingField->name)

@section('content')
<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1>{{ $contactFamilyLoggingField->name }}</h1>
        <p class="text-muted mb-0">{{ ucfirst($contactFamilyLoggingField->field_type) }} field</p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('contact-family-logging-fields.edit', $contactFamilyLoggingField) }}" class="btn btn-outline-primary">Edit</a>
        <a href="{{ route('contact-family-logging-fields.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>
</div>

<div class="card mb-3">
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Slug</dt>
            <dd class="col-sm-9"><code>{{ $contactFamilyLoggingField->slug }}</code></dd>
            <dt class="col-sm-3">Help Text</dt>
            <dd class="col-sm-9">{{ $contactFamilyLoggingField->help_text ?: '—' }}</dd>
            <dt class="col-sm-3">Sort Order</dt>
            <dd class="col-sm-9">{{ $contactFamilyLoggingField->sort_order ?? '—' }}</dd>
            <dt class="col-sm-3">Usage</dt>
            <dd class="col-sm-9">{{ $contactFamilyLoggingField->contactFamilies->count() }} contact family/families</dd>
        </dl>
    </div>
</div>

@if($contactFamilyLoggingField->contactFamilies->isNotEmpty())
    <div class="card">
        <div class="card-header"><h5 class="mb-0">Assigned Contact Families</h5></div>
        <ul class="list-group list-group-flush">
            @foreach($contactFamilyLoggingField->contactFamilies as $family)
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <span>{{ $family->name }}</span>
                    @if($family->pivot->is_required)
                        <span class="badge bg-warning text-dark">Required</span>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
@endsection

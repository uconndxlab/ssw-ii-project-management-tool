@extends('layouts.app')

@section('title', 'Create Activity Type')

@section('content')
<div class="row mb-4">
    <div class="col-12">
        <h1>Create Activity Type</h1>
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

                <form method="POST" action="{{ route('activity-types.store') }}">
                    @csrf

                    <div class="mb-3">
                        <label for="contact_family_id" class="form-label">Contact Family <span class="text-danger">*</span></label>
                        <select class="form-select @error('contact_family_id') is-invalid @enderror" 
                                id="contact_family_id" 
                                name="contact_family_id" 
                                required>
                            <option value="">Select contact family...</option>
                            @foreach($contactFamilies as $family)
                                <option value="{{ $family->id }}" {{ old('contact_family_id') == $family->id ? 'selected' : '' }}>
                                    {{ $family->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('contact_family_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

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
                        <div class="form-text">Used to order activity types in dropdowns. Lower numbers appear first.</div>
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
                        <div class="form-text">Only active activity types appear in activity forms.</div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="{{ route('activity-types.index') }}" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Create Activity Type</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

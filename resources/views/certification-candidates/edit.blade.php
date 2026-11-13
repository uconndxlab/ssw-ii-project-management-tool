@extends('layouts.app')
@section('title', 'Edit Candidate')
@section('content')
<x-form-shell>
    <x-form-errors />
    <form method="POST" action="{{ route('certification-candidates.update', $candidate) }}" id="candidate-edit-form">
        @csrf
        @method('PUT')
        <x-page-header context="form" form-id="candidate-edit-form" save-label="Save Candidate" :last-saved-at="$candidate->updated_at" :title="old('name', $candidate->name)" entity-type="Candidate" mode="edit" />
        <x-section-card title="Information">
            <p class="text-muted small">These shared details apply to every agreement this candidate is on.</p>
            @foreach(['name' => ['Name', 'text', true], 'email' => ['Email', 'email', true], 'phone' => ['Phone', 'tel', false], 'start_date' => ['Start date', 'date', false]] as $field => [$label, $type, $required])
                <x-form-field :label="$label" :for="$field" :name="$field" :required="$required">
                    <input class="form-control @error($field) is-invalid @enderror" type="{{ $type }}" id="{{ $field }}" name="{{ $field }}" value="{{ old($field, $field === 'start_date' ? $candidate->start_date?->format('Y-m-d') : $candidate->{$field}) }}" @required($required) @if($type !== 'date') maxlength="255" @endif>
                </x-form-field>
            @endforeach
            <x-form-field label="Notes" for="notes" name="notes"><textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="4" maxlength="10000">{{ old('notes', $candidate->notes) }}</textarea></x-form-field>
            <x-form-options><x-form-switch name="active" label="Active" :checked="old('active', $candidate->active)" class="mb-0" /></x-form-options>
        </x-section-card>
    </form>
</x-form-shell>
@endsection

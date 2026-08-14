@extends('layouts.app')

@section('title', 'Edit Agreement')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('agreements.update', $agreement) }}" id="agreements-edit-form" enctype="multipart/form-data">
        @csrf
        @method('PUT')
        <x-page-header
            context="form"
            :title="old('name', $agreement->name)"
            entity-type="Agreement"
            mode="edit"
        />
        @include('agreements.partials.form-fields', ['agreement' => $agreement])
    </form>
</x-form-shell>
<x-save-bar form-id="agreements-edit-form" save-label="Save Agreement" :last-saved-at="$agreement->updated_at" />
@endsection

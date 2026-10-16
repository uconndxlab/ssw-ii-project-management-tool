@extends('layouts.app')

@section('title', 'Create Agreement')

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('agreements.store') }}" id="agreements-create-form" enctype="multipart/form-data">
        @csrf
        <x-page-header form-id="agreements-create-form" save-label="Create Agreement" context="form" entity-type="Agreement" mode="create" />
        @include('agreements.partials.form-fields', ['agreement' => null])
    </form>
</x-form-shell>
@endsection

@extends('layouts.app')

@section('title', 'Edit Agreement')

@section('content')
<div class="row justify-content-center">
    <div class="col-lg-10">
        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('agreements.update', $agreement) }}" id="agreements-edit-form" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <x-page-header
                context="form"
                :title="old('name', $agreement->name)"
                entity-type="Agreement"
                mode="edit"
                :show-active="true"
                :active-default="old('active', $agreement->active)"
                active-help="Inactive agreements are hidden from lists and activity logging. Assignments and historical data are kept."
            />
            @include('agreements.partials.form-fields', ['agreement' => $agreement])
        </form>
    </div>
</div>
<x-save-bar form-id="agreements-edit-form" save-label="Save Agreement" :last-saved-at="$agreement->updated_at" />
@endsection

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
            <x-form-page-header
                entity-type="Agreement"
                :entity-type-badge-class="\App\Support\EntityBadge::typeClasses('agreement')"
                mode="edit"
                :record-name="old('name', $agreement->name)"
                :back-route="route('agreements.show', $agreement)"
                back-label="Back to agreement"
                :show-active="true"
                :active-default="old('active', $agreement->active)"
                active-help="Inactive agreements are hidden from lists and activity logging. Assignments and historical data are kept."
            />
            @include('agreements.partials.form-fields', ['agreement' => $agreement])
        </form>
    </div>
</div>
<x-save-bar form-id="agreements-edit-form" cancel-url="{{ route('agreements.show', $agreement) }}" save-label="Save Agreement" :last-saved-at="$agreement->updated_at" />
@endsection

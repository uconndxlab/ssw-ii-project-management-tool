@extends('layouts.app')

@section('title', 'Edit Contact Family')

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

        <form method="POST" action="{{ route('contact-families.update', $contactFamily) }}" id="contact-families-edit-form">
            @csrf
            @method('PUT')
            <x-page-header
                context="form"
                :title="old('name', $contactFamily->name)"
                entity-type="Contact Family"
                mode="edit"
                :show-active="true"
                :active-default="old('active', $contactFamily->active)"
                active-help="Only active contact families appear in activity forms."
            />
            @include('admin.contact-families.partials.form-fields', ['contactFamily' => $contactFamily])
        </form>
    </div>
</div>
<x-save-bar form-id="contact-families-edit-form" save-label="Save Contact Family" :last-saved-at="$contactFamily->updated_at" />
@endsection

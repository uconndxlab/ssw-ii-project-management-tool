@extends('layouts.app')

@section('title', 'Create Contact Family')

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

        <form method="POST" action="{{ route('contact-families.store') }}" id="contact-families-create-form">
            @csrf
            <x-form-page-header
                entity-type="Contact Family"
                entity-type-badge-class="bg-info text-dark"
                mode="create"
                :show-active="true"
                :active-default="old('active', true)"
                active-help="Only active contact families appear in activity forms."
            />
            @include('admin.contact-families.partials.form-fields')
        </form>
    </div>
</div>
<x-save-bar form-id="contact-families-create-form" cancel-url="{{ route('contact-families.index') }}" save-label="Create Contact Family" />
@endsection

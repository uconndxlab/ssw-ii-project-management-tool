@extends('layouts.app')

@section('title', 'Create Activity Family')

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
            <x-page-header
                context="form"
                entity-type="Activity Family"
                mode="create"
                :show-active="true"
                :active-default="old('active', true)"
                active-help="Only active activity families appear in activity forms."
            />
            @include('admin.contact-families.partials.form-fields')
        </form>
    </div>
</div>
<x-save-bar form-id="contact-families-create-form" save-label="Create Activity Family" />
@endsection

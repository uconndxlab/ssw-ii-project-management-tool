@extends('layouts.app')

@section('title', 'Edit Contact Family')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-lg-10">
        <h1>Edit Contact Family</h1>
    </div>
</div>

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
            @include('admin.contact-families.partials.form-fields', ['contactFamily' => $contactFamily])
        </form>
    </div>
</div>
<x-save-bar form-id="contact-families-edit-form" cancel-url="{{ route('contact-families.index') }}" save-label="Save Contact Family" :last-saved-at="$contactFamily->updated_at" />
@endsection

@extends('layouts.app')

@section('title', 'Create Contact Family')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-lg-8">
        <h1>Create Contact Family</h1>
    </div>
</div>

<div class="row justify-content-center">
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

                <form method="POST" action="{{ route('contact-families.store') }}" id="contact-families-create-form">
                    @csrf
                    @include('admin.contact-families.partials.form-fields')

                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="contact-families-create-form" cancel-url="{{ route('contact-families.index') }}" save-label="Create Contact Family" />
@endsection

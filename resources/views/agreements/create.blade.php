@extends('layouts.app')

@section('title', 'Create Agreement')

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

        <form method="POST" action="{{ route('agreements.store') }}" id="agreements-create-form" enctype="multipart/form-data">
            @csrf
            <x-form-page-header
                entity-type="Agreement"
                :entity-type-badge-class="\App\Support\EntityBadge::typeClasses('agreement')"
                mode="create"
                :back-route="route('agreements.index')"
                back-label="All Agreements"
            />
            @include('agreements.partials.form-fields', ['agreement' => null])
        </form>
    </div>
</div>
<x-save-bar form-id="agreements-create-form" cancel-url="{{ route('agreements.index') }}" save-label="Create Agreement" />
@endsection

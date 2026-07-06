@extends('layouts.app')

@section('title', 'Edit Agreement')

@section('content')

<div class="row justify-content-center mb-4">
    <div class="col-md-8">
        <h1>Edit Agreement</h1>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card mb-4">
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

                <form method="POST" action="{{ route('agreements.update', $agreement) }}" id="agreements-edit-form" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    @include('agreements.partials.form-fields', ['agreement' => $agreement])

                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="agreements-edit-form" cancel-url="{{ route('agreements.index') }}" save-label="Save Agreement" :last-saved-at="$agreement->updated_at" />

@endsection

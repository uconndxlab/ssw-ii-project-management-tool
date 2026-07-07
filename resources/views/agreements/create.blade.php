@extends('layouts.app')

@section('title', 'Create Agreement')

@section('content')
<div class="row justify-content-center mb-4">
    <div class="col-md-10">
        <h1>Create Agreement</h1>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-10">
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

                <form method="POST" action="{{ route('agreements.store') }}" id="agreements-create-form" enctype="multipart/form-data">
                    @csrf
                    @include('agreements.partials.form-fields', ['agreement' => null])

                </form>
            </div>
        </div>
    </div>
</div>
<x-save-bar form-id="agreements-create-form" cancel-url="{{ route('agreements.index') }}" save-label="Create Agreement" />
@endsection

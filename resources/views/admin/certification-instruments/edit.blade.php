@extends('layouts.app')

@section('title', 'Edit '.$certificationInstrument->type->label())

@section('content')
<x-form-shell>
    <x-form-errors />

    <form method="POST" action="{{ route('certification-instruments.update', $certificationInstrument) }}" id="certification-instruments-edit-form">
        @csrf
        @method('PUT')
        <x-page-header form-id="certification-instruments-edit-form" :save-label="'Save '.$certificationInstrument->type->label()" context="form" :entity-type="$certificationInstrument->type->label()" mode="edit" />
        @include('admin.certification-instruments.partials.form-fields')
    </form>
</x-form-shell>
@endsection

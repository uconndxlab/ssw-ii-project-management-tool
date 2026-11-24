@extends('layouts.app')
@section('title', 'Award history — '.$enrollment->candidate->name)
@section('content')
@php
    $target = route('certificate-enrollments.show', ['certificateEnrollment' => $enrollment, 'agreement_id' => request()->integer('agreement_id') ?: null]);
    $rows = old('rows', $awards->map(fn ($award) => ['id' => $award->id, 'awarded_at' => $award->awarded_at?->toDateString(), 'expires_at' => $award->expires_at?->toDateString(), 'expiry_overridden' => $award->expiry_overridden, 'originally_awarded_by' => $award->originally_awarded_by])->all()) ?: [[]];
@endphp
<x-form-shell>
    <x-form-errors />
    @if($blocker)
        <x-page-header title="Award history" :description="$enrollment->candidate->name.' · '.$enrollment->certificate->name" />
        <x-form-note title="History unavailable" tone="warning">{{ $blocker }} <a href="{{ $target }}">Back to progress</a></x-form-note>
    @else
    <form method="POST" id="history-form" action="{{ route('certificate-enrollments.history.update', ['certificateEnrollment' => $enrollment, 'agreement_id' => request()->integer('agreement_id') ?: null]) }}">
        @csrf @method('PUT')
        <input type="hidden" name="cycle" value="{{ $enrollment->cycle }}">
        <x-page-header context="form" mode="edit" :title="$awards->isEmpty() ? 'Record award history' : 'Edit award history'" :description="$enrollment->candidate->name.' · '.$enrollment->certificate->name" form-id="history-form" save-label="Save history" />
        <x-form-note title="Before you save">Enter every past award in date order, starting with the initial award. These awards can't be removed once saved. Dates and details can still be edited. Check everything is correct.</x-form-note>
        @if(! $prerequisites['satisfied'])
            <x-form-note title="Missing prerequisite" tone="warning">This candidate has no valid {{ collect($prerequisites['items'])->whereNull('award')->pluck('certificate.name')->join(', ', $prerequisites['mode'] === 'any' ? ' or ' : ' and ') }} award, which this certificate requires. You can still save this history.</x-form-note>
        @endif
        <x-section-card title="Past awards">
            <x-repeater-rows name="rows" :rows="$rows" :sortable="false" :scroll="false" add-label="Add award" :template="view('certification.partials.history-row', ['row' => [], 'index' => '__INDEX__', 'enrollment' => $enrollment])->render()">
                @foreach($rows as $index => $row)@include('certification.partials.history-row', compact('index', 'row'))@endforeach
            </x-repeater-rows>
            <div data-progress-warning class="alert alert-warning small mt-3 mb-0" role="status" hidden></div>
        </x-section-card>
        <x-section-card title="Recording details">
            <x-date-override-field name="enrolled_at" id="history-enrolled-at" label="Enrollment date" :value="old('enrolled_at', $enrollment->enrolled_at?->toDateString())" :calculated="$enrollment->enrolled_at?->toDateString() ?? ''" :overridden="old('enrolled_at', $enrollment->enrolled_at?->toDateString()) !== $enrollment->enrolled_at?->toDateString()" revert-label="Undo change" />
            <x-form-field label="Reason" for="history-reason" name="reason" required>
                <textarea class="form-control" id="history-reason" name="reason" rows="3" maxlength="10000" required>{{ old('reason') }}</textarea>
            </x-form-field>
        </x-section-card>

        @php
            $certificationHistoryConfig = [
                'months' => $enrollment->certificate->validity_months,
                'existingCount' => $awards->count(),
                'hasProgress' => ! $enrollment->isUnstarted(),
                'cycle' => $enrollment->cycle,
            ];
        @endphp
        <script type="application/json" data-certification-history-config>@json($certificationHistoryConfig)</script>
    </form>
    @endif
</x-form-shell>
@endsection
@if(! $blocker)
    @push('scripts')
        @vite('resources/js/certification-history.js')
    @endpush
@endif

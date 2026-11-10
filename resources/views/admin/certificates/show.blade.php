@extends('layouts.app')

@section('title', $certificate->name)

@section('content')
<x-page-header context="show" :title="$certificate->name" entity-type="Certificate" :active="$certificate->active"
    :action-url="auth()->user()->can('update', $certificate) ? route('certificates.edit', $certificate) : null" />

<x-section-tabs :tabs="['overview' => 'Overview', 'requirements' => 'Requirements', 'candidates' => 'Candidates']" label="Certificate sections" hash>
    <section id="section-overview" data-section="overview" class="mt-3">
        @include('admin.certificates.partials.show-overview')
    </section>
    <section id="section-requirements" data-section="requirements" class="mt-3" hidden>
        @include('admin.certificates.partials.show-requirements')
    </section>
    <section id="section-candidates" data-section="candidates" class="mt-3" hidden>
        @include('admin.certificates.partials.show-candidates')
    </section>
</x-section-tabs>
@endsection

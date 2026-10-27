@extends('layouts.app')

@section('title', $loggingField->name)

@section('content')
@php
    $fieldOptions = $loggingField->normalizedOptions();
@endphp
<x-page-header
    context="show"
    :title="$loggingField->name"
    entity-type="Logging Field"
    :active="$loggingField->is_active"
    :action-url="auth()->user()->can('update', $loggingField) ? route('logging-fields.edit', $loggingField) : null"
>
    <x-slot:badges>
        <span class="badge app-tag">Field type: {{ $loggingField->fieldTypeLabel() }}</span>
    </x-slot:badges>
</x-page-header>

<x-entity-show>
    <x-slot:summary>
        <dl class="app-record-metrics">
            <div><dt>Agreements</dt><dd>{{ $loggingField->agreements->count() }}</dd></div>
            <div><dt>Activity families</dt><dd>{{ $loggingField->contactFamilies->count() }}</dd></div>
        </dl>
        <dl class="row mb-0 mt-3">
            <dt class="col-5 text-muted fw-normal small">Available in</dt>
            <dd class="col-7 mb-2">
                <div class="d-flex flex-wrap gap-1">
                    @if($loggingField->available_in_agreements)<span class="badge app-tag">Agreements</span>@endif
                    @if($loggingField->available_in_contact_families)<span class="badge app-tag">Activity Families</span>@endif
                    @if($loggingField->available_in_activities)<span class="badge app-tag">Activity Types</span>@endif
                </div>
            </dd>
            <dt class="col-5 text-muted fw-normal small">Sort order</dt>
            <dd class="col-7 mb-2">{{ $loggingField->sort_order ?? 'None (alphabetical)' }}</dd>
            <dt class="col-5 text-muted fw-normal small">Layout</dt>
            <dd class="col-7 mb-0">{{ $loggingField->is_full_width ? 'Full width' : 'Half width' }}</dd>
        </dl>
        @if($loggingField->usesOptions() && $fieldOptions)
            <x-relationship-scroll-panel title="Options" :count="count($fieldOptions)" collapsible collapsed class="mt-3">
                @foreach($fieldOptions as $option)
                    <x-relationship-ledger-row :title="$option['label']" />
                @endforeach
            </x-relationship-scroll-panel>
        @endif
        @if($loggingField->help_text)
            <hr>
            <h3 class="app-subhead mb-2">Help text</h3>
            <p class="small mb-0">{{ $loggingField->help_text }}</p>
        @endif
    </x-slot:summary>

    <x-slot:relationships>
        <div class="row g-4">
            <div class="col-md-6">
                <x-relationship-scroll-panel title="Agreements" kind="agreement" :count="$loggingField->agreements->count()" collapsible>
                    @foreach($loggingField->agreements as $agreement)
                        <x-relationship-ledger-row
                            :title="$agreement->name"
                            :href="$agreement->isLinkable() ? route('agreements.show', $agreement) : null"
                            :context-badges="$agreement->pivot->is_required ? ['Required'] : []"
                        />
                    @endforeach
                </x-relationship-scroll-panel>
            </div>
            <div class="col-md-6">
                <x-relationship-scroll-panel title="Activity Families" :count="$loggingField->contactFamilies->count()" collapsible>
                    @foreach($loggingField->contactFamilies as $family)
                        <x-relationship-ledger-row :title="$family->name" :context-badges="$family->pivot->is_required ? ['Required'] : []" />
                    @endforeach
                </x-relationship-scroll-panel>
            </div>
        </div>
        @can('delete', $loggingField)
            @if($loggingField->agreements->isEmpty() && $loggingField->contactFamilies->isEmpty())
                <hr>
                <h3 class="h6 text-danger mb-2">Delete This Field?</h3>
                <p class="small text-muted mb-3">This field is not currently in use and can be safely deleted.</p>
                <form method="POST" action="{{ route('logging-fields.destroy', $loggingField) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-danger"
                            data-confirm
                            data-confirm-title="Delete logging field?"
                            data-confirm-record="{{ $loggingField->name }}"
                            data-confirm-message="This action cannot be undone."
                            data-confirm-action="Delete field">Delete Field</button>
                </form>
            @endif
        @endcan
    </x-slot:relationships>
</x-entity-show>
@endsection

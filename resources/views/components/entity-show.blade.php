{{--
    Shared show-page body layout component.

    Props:
        mainCardTitle - Title for the primary right-side card
        activityFirst - Whether recent activity appears before the main card

    Named slots:
        $summary       - Core field list (left column)
        $relationships - Related entity cards (right column, top)
        $activity      - Recent activity content (right column, bottom)
--}}
@props([
    'mainCardTitle'  => 'Relationships',
    'activityFirst'  => false,
])

@php
    $relationshipsHeaderMetaFilled = isset($relationshipsHeaderMeta) && filled(trim((string) $relationshipsHeaderMeta));
    $relationshipsHeaderActionsFilled = isset($relationshipsHeaderActions) && filled(trim((string) $relationshipsHeaderActions));
    $activityHeaderMetaFilled = isset($activityHeaderMeta) && filled(trim((string) $activityHeaderMeta));
    $activityHeaderActionsFilled = isset($activityHeaderActions) && filled(trim((string) $activityHeaderActions));
    $sections = isset($activity) ? ($activityFirst ? ['activity', 'relationships'] : ['relationships', 'activity']) : ['relationships'];
@endphp

<div class="row g-4">
    {{-- Summary column (narrow) --}}
    <div class="col-lg-4">
        <div class="card shadow-sm entity-show-card">
            <div class="card-header py-2 px-3">
                <h2 class="h5 mb-0">Summary</h2>
            </div>
            <div class="card-body" data-record-summary>
                {{ $summary }}
            </div>
        </div>
    </div>

    <div class="col-lg-8 d-flex flex-column gap-4">
        @isset($beforeMain)
            {{ $beforeMain }}
        @endisset
        @foreach($sections as $section)
            @php
                $isActivity = $section === 'activity';
                $headerMetaFilled = $isActivity ? $activityHeaderMetaFilled : $relationshipsHeaderMetaFilled;
                $headerActionsFilled = $isActivity ? $activityHeaderActionsFilled : $relationshipsHeaderActionsFilled;
            @endphp
            <div class="card shadow-sm entity-show-card">
                <div class="card-header py-2 px-3 d-flex align-items-center gap-2 flex-wrap">
                    <h2 class="h5 mb-0">{{ $isActivity ? 'Recent activity' : $mainCardTitle }}</h2>
                    @if($headerMetaFilled)
                        <div class="d-flex align-items-center gap-2 flex-wrap">{{ $isActivity ? $activityHeaderMeta : $relationshipsHeaderMeta }}</div>
                    @endif
                    @if($headerActionsFilled)
                        <div class="ms-auto d-flex align-items-center gap-2 flex-wrap">{{ $isActivity ? $activityHeaderActions : $relationshipsHeaderActions }}</div>
                    @endif
                </div>
                <div class="card-body">
                    {{ $isActivity ? $activity : $relationships }}
                </div>
            </div>
        @endforeach
    </div>
</div>

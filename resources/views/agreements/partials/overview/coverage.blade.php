<x-record-section title="Coverage">
    <div class="d-grid gap-3">
        @foreach(['projects' => 'Projects', 'programs' => 'Programs', 'states' => 'States'] as $relation => $label)
            <div>
                <div class="d-flex align-items-center gap-2 mb-2"><h3 class="h6 small mb-0">{{ $label }}</h3><span class="app-count">{{ $agreement->$relation->count() }}</span></div>
                <div class="d-flex flex-wrap gap-1">
                    @forelse($agreement->$relation->sortBy('name') as $record)
                        @can('view', $record)<a class="badge app-chip" href="{{ route($relation.'.show', $record) }}">{{ $record->name }}</a>@else<span class="app-chip app-plain">{{ $record->name }}</span>@endcan
                    @empty
                        <span class="text-muted small">None</span>
                    @endforelse
                </div>
                @if($relation === 'programs' && $activityOnlyPrograms->isNotEmpty())
                    <p class="small text-muted mb-0 mt-2">Also logged under
                        @foreach($activityOnlyPrograms as $program)
                            @can('view', $program)<a class="app-link" href="{{ route('programs.show', $program) }}">{{ $program->name }}</a>@else{{ $program->name }}@endcan{{ $loop->last ? '' : ',' }}
                        @endforeach
                    </p>
                @endif
            </div>
        @endforeach
    </div>
</x-record-section>

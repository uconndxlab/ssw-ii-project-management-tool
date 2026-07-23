@if($payorSources->isNotEmpty() || $payeeSources->isNotEmpty())
    <div class="{{ $hasLoggingValuesAbove ? 'mt-3 pt-3 border-top' : '' }}">
        @if($payorSources->isNotEmpty())
            <div class="{{ $payeeSources->isNotEmpty() ? 'mb-3' : 'mb-0' }}">
                <div class="small text-muted mb-2">Payor Sources</div>
                <ul class="list-unstyled mb-0">
                    @foreach($payorSources as $source)
                        @php $entity = $source->resolveSourceModel(); @endphp
                        <li class="small py-1">
                            <span class="fw-semibold">{{ $entity?->name ?? 'Unknown source' }}</span>
                            <span class="badge {{ $source->source_type === 'organization' ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-primary-subtle text-primary-emphasis border border-primary-subtle' }} ms-1">{{ $source->source_type === 'organization' ? 'Organization' : 'User' }}</span>
                            @if($entity?->kfs_number)
                                <span class="small text-muted opacity-75 ms-1">| {{ $entity->kfs_number }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if($payeeSources->isNotEmpty())
            <div>
                <div class="small text-muted mb-2">Payee Sources</div>
                <ul class="list-unstyled mb-0">
                    @foreach($payeeSources as $source)
                        @php $entity = $source->resolveSourceModel(); @endphp
                        <li class="small py-1">
                            <span class="fw-semibold">{{ $entity?->name ?? 'Unknown source' }}</span>
                            <span class="badge {{ $source->source_type === 'organization' ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-primary-subtle text-primary-emphasis border border-primary-subtle' }} ms-1">{{ $source->source_type === 'organization' ? 'Organization' : 'User' }}</span>
                            @if($entity?->kfs_number)
                                <span class="small text-muted opacity-75 ms-1">| {{ $entity->kfs_number }}</span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@endif

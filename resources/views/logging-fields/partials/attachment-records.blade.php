<div class="small mb-2">
    <div class="fw-semibold">{{ ucfirst(Str::plural($group['label'])) }}</div>
    <ul class="list-unstyled mb-0">
        @foreach($group['records'] as $record)
            <li data-attachment-record="{{ $record['id'] }}"><a href="{{ $record['url'] }}">{{ $record['name'] }}</a></li>
        @endforeach
        <li data-attachment-hidden @if(!$group['hidden']) hidden @endif>+{{ $group['hidden'] }} {{ Str::plural($group['label'], $group['hidden']) }} you don't have access to</li>
    </ul>
</div>

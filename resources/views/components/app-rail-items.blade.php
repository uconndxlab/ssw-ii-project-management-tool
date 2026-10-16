@props(['items'])

<ul class="app-rail-list">
    @foreach ($items as $item)
        @php($inSection = App\Support\AppNav::isActive($item['active']))
        @php($onIndex = request()->routeIs($item['route']))
        <li>
            <a @class(['app-rail-link', 'is-section' => $inSection])
               href="{{ route($item['route']) }}"
               @if($inSection) aria-current="{{ $onIndex ? 'page' : 'true' }}" @endif>
                <i class="bi {{ $item['icon'] }}" aria-hidden="true"></i>
                <span class="app-rail-label">{{ $item['label'] }}</span>
            </a>
        </li>
    @endforeach
</ul>

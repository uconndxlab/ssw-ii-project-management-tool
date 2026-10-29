{{-- Flash messages become toasts. toasts.js moves each template into the stack after load and announces it. --}}
@if(session('success') || session('status'))
    <template data-toast="success">{{ session('success') ?? session('status') }}</template>
@endif

@if(session('allocation_warnings') && count(session('allocation_warnings')) > 0)
    <template data-toast="warning" data-toast-title="Deliverable allocation needs attention">
        <ul>
            @foreach(session('allocation_warnings') as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </template>
@endif

@if(session('error'))
    <template data-toast="danger">{{ session('error') }}</template>
@endif

<div class="app-toasts" data-app-toasts data-toast-timeout="6000"></div>

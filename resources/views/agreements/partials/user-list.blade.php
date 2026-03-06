@foreach($agreement->users as $user)
<div class="list-group-item d-flex justify-content-between align-items-center">
    <div>
        <strong>{{ $user->name }}</strong>
        <small class="text-muted d-block">{{ $user->email }}</small>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge 
            @if($user->role === 'admin') bg-danger
            @elseif($user->role === 'consultant') bg-info
            @else bg-secondary
            @endif">
            {{ ucfirst($user->role) }}
        </span>
        @if(auth()->user()->isAdmin())
        <button type="button" 
                class="btn btn-sm btn-outline-danger"
                hx-delete="{{ route('agreements.remove-user', [$agreement, $user]) }}"
                hx-target="#user-list"
                hx-swap="innerHTML"
                hx-confirm="Remove {{ $user->name }} from this agreement?">
            Remove
        </button>
        @endif
    </div>
</div>
@endforeach

@if($agreement->users->count() === 0)
<div class="text-muted text-center py-3">
    No team members assigned
</div>
@endif

<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Quick Search</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('agreements.index') }}" method="get" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search agreements, organizations..." value="{{ request('search', '') }}">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
        <small class="text-muted d-block mt-2">💡 Tip: Search will look across agreements, organizations, states, and activity types.</small>
    </div>
</div>

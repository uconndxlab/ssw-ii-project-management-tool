<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Quick Search</h5>
    </div>
    <div class="card-body">
        <form action="{{ route('search') }}" method="get" class="d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Search agreements, organizations, people..." value="{{ request('q', '') }}">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
        <small class="text-muted d-block mt-2">💡 Tip: Search across agreements, organizations, and people.</small>
    </div>
</div>

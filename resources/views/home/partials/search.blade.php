<div class="card shadow-sm mb-4">
    <div class="card-header">
        <h2 class="h5 mb-0">Quick Search</h2>
    </div>
    <div class="card-body">
        <form action="{{ route('search') }}" method="get" class="d-flex gap-2">
            <label for="home-search-query" class="visually-hidden">Search</label>
            <input type="text" id="home-search-query" name="q" class="form-control form-control-sm" placeholder="Search agreements, organizations, people…" value="{{ request('q', '') }}">
            <button type="submit" class="btn btn-primary btn-sm">Search</button>
        </form>
    </div>
</div>

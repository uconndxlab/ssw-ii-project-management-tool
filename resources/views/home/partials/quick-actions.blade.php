<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0">Quick Actions</h5>
    </div>
    <div class="card-body">
        <div class="d-grid gap-2 d-sm-flex flex-wrap">
            <a href="{{ route('activities.create') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle"></i> Log Activity
            </a>
            @can('create', App\Models\Agreement::class)
            <a href="{{ route('agreements.create') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-file-earmark-plus"></i> Create Agreement
            </a>
            @endcan
            @can('create', App\Models\Organization::class)
            <a href="{{ route('organizations.create') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-building"></i> Add Organization
            </a>
            @endcan
            @can('create', App\Models\User::class)
            <a href="{{ route('admin.users.create') }}" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-person-plus"></i> Add User
            </a>
            @endcan
            @can('viewAny', App\Models\Agreement::class)
            <a href="{{ route('agreements.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-list"></i> View Agreements
            </a>
            @endcan
        </div>
    </div>
</div>

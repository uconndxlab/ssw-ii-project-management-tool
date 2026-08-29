@php
    $superviseesIndex = $superviseesIndex ?? false;
    $usersIndexRoute = $superviseesIndex ? 'supervisees.index' : 'admin.users.index';
@endphp
<form id="user-filters"
      data-table-filter-form
      hx-get="{{ route($usersIndexRoute) }}"
      hx-target="#users-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search by name, email, or PO number…" value="{{ request('search') }}"
                   hx-get="{{ route($usersIndexRoute) }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#users-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#user-filters">
        </div>
        <div class="col-md-2">
            <select name="access_profile" class="form-select form-select-sm"
                    hx-get="{{ route($usersIndexRoute) }}" hx-trigger="change"
                    hx-target="#users-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#user-filters">
                <option value="">All profiles</option>
                @foreach(\App\Enums\AccessProfile::cases() as $profile)
                    <option value="{{ $profile->value }}" @selected(request('access_profile') === $profile->value)>{{ $profile->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="project_id" class="form-select form-select-sm"
                    hx-get="{{ route($usersIndexRoute) }}" hx-trigger="change"
                    hx-target="#users-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#user-filters">
                <option value="">All Projects</option>
                @foreach($filterProjects ?? [] as $project)
                    <option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="program_id" class="form-select form-select-sm"
                    hx-get="{{ route($usersIndexRoute) }}" hx-trigger="change"
                    hx-target="#users-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#user-filters">
                <option value="">All Programs</option>
                @foreach($filterPrograms ?? [] as $program)
                    <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>
                        {{ $program->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm"
                    hx-get="{{ route($usersIndexRoute) }}" hx-trigger="change"
                    hx-target="#users-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#user-filters">
                <option value="">All Statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <x-table-filter-clear
            :href="route($usersIndexRoute)"
            :filter-keys="['search', 'access_profile', 'project_id', 'program_id', 'status']"
        />
    </div>
    <input type="hidden" name="sort"      value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>

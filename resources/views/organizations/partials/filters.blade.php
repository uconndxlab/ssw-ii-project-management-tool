<form id="organization-filters"
      data-table-filter-form
      hx-get="{{ route('organizations.index') }}"
      hx-target="#organizations-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search organizations…" value="{{ request('search') }}"
                   hx-get="{{ route('organizations.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#organizations-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#organization-filters">
        </div>
        <div class="col-md-2">
            <select name="state_id" class="form-select form-select-sm"
                    hx-get="{{ route('organizations.index') }}" hx-trigger="change"
                    hx-target="#organizations-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#organization-filters">
                <option value="">All States</option>
                @foreach($states as $state)
                    <option value="{{ $state->id }}" @selected((string) request('state_id') === (string) $state->id)>
                        {{ $state->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm"
                    hx-get="{{ route('organizations.index') }}" hx-trigger="change"
                    hx-target="#organizations-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#organization-filters">
                <option value="">All Statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="project_id" class="form-select form-select-sm"
                    hx-get="{{ route('organizations.index') }}" hx-trigger="change"
                    hx-target="#organizations-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#organization-filters">
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
                    hx-get="{{ route('organizations.index') }}" hx-trigger="change"
                    hx-target="#organizations-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#organization-filters">
                <option value="">All Programs</option>
                @foreach($filterPrograms ?? [] as $program)
                    <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>
                        {{ $program->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <x-table-filter-clear
            :href="route('organizations.index')"
            :filter-keys="['search', 'state_id', 'status', 'project_id', 'program_id']"
        />
    </div>
    <input type="hidden" name="sort"      value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>

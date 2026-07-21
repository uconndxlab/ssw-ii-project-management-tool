<form id="team-filters"
      data-table-filter-form
      hx-get="{{ route('teams.index') }}"
      hx-target="#teams-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search teams…" value="{{ request('search') }}"
                   hx-get="{{ route('teams.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#teams-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#team-filters">
        </div>
        <div class="col-md-2">
            <select name="active" class="form-select form-select-sm"
                    hx-get="{{ route('teams.index') }}" hx-trigger="change"
                    hx-target="#teams-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#team-filters">
                <option value="">All Teams</option>
                <option value="1" @selected(request('active') === '1')>Active Only</option>
                <option value="0" @selected(request('active') === '0')>Inactive Only</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="project_id" class="form-select form-select-sm"
                    hx-get="{{ route('teams.index') }}" hx-trigger="change"
                    hx-target="#teams-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#team-filters">
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
                    hx-get="{{ route('teams.index') }}" hx-trigger="change"
                    hx-target="#teams-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#team-filters">
                <option value="">All Programs</option>
                @foreach($filterPrograms ?? [] as $program)
                    <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>
                        {{ $program->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <x-table-filter-clear
            :href="route('teams.index')"
            :filter-keys="['search', 'active', 'project_id', 'program_id']"
        />
    </div>
    <input type="hidden" name="sort"      value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>

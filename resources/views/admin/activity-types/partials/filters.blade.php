<form id="activity-type-filters"
      data-table-filter-form
      hx-get="{{ route('activity-types.index') }}"
      hx-target="#activity-types-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search activity types…" value="{{ request('search') }}"
                   hx-get="{{ route('activity-types.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#activity-types-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#activity-type-filters">
        </div>
        <div class="col-md-2">
            <select name="contact_family_id" class="form-select form-select-sm"
                    hx-get="{{ route('activity-types.index') }}" hx-trigger="change"
                    hx-target="#activity-types-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#activity-type-filters">
                <option value="">All Contact Families</option>
                @foreach($contactFamilies as $cf)
                    <option value="{{ $cf->id }}" @selected((string) request('contact_family_id') === (string) $cf->id)>
                        {{ $cf->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="project_id" class="form-select form-select-sm"
                    hx-get="{{ route('activity-types.index') }}" hx-trigger="change"
                    hx-target="#activity-types-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#activity-type-filters">
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
                    hx-get="{{ route('activity-types.index') }}" hx-trigger="change"
                    hx-target="#activity-types-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#activity-type-filters">
                <option value="">All Programs</option>
                @foreach($filterPrograms ?? [] as $program)
                    <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>
                        {{ $program->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="active" class="form-select form-select-sm"
                    hx-get="{{ route('activity-types.index') }}" hx-trigger="change"
                    hx-target="#activity-types-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#activity-type-filters">
                <option value="">All Statuses</option>
                <option value="1" @selected(request('active') === '1')>Active</option>
                <option value="0" @selected(request('active') === '0')>Inactive</option>
            </select>
        </div>
        <x-table-filter-clear
            :href="route('activity-types.index')"
            :filter-keys="['search', 'contact_family_id', 'project_id', 'program_id', 'active']"
        />
    </div>
    <input type="hidden" name="sort"      value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>

<form id="certificate-filters"
      data-table-filter-form
      hx-get="{{ route('certificates.index') }}"
      hx-target="#certificates-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search certificates…" value="{{ request('search') }}"
                   hx-get="{{ route('certificates.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#certificates-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#certificate-filters">
        </div>
        <div class="col-md-3">
            <select name="project_id" class="form-select form-select-sm"
                    hx-get="{{ route('certificates.index') }}" hx-trigger="change"
                    hx-target="#certificates-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#certificate-filters">
                <option value="">All Projects</option>
                @foreach($filterProjects ?? [] as $project)
                    <option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select name="program_id" class="form-select form-select-sm"
                    hx-get="{{ route('certificates.index') }}" hx-trigger="change"
                    hx-target="#certificates-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#certificate-filters">
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
                    hx-get="{{ route('certificates.index') }}" hx-trigger="change"
                    hx-target="#certificates-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#certificate-filters">
                <option value="">All Statuses</option>
                <option value="1" @selected(request('active') === '1')>Active</option>
                <option value="0" @selected(request('active') === '0')>Inactive</option>
            </select>
        </div>
        <x-table-filter-clear
            :href="route('certificates.index')"
            :filter-keys="['search', 'project_id', 'program_id', 'active']"
        />
    </div>
    <input type="hidden" name="sort"      value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>

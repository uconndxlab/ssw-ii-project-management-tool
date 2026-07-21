<form id="cf-filters"
      data-table-filter-form
      hx-get="{{ route('contact-families.index') }}"
      hx-target="#cf-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search contact families…" value="{{ request('search') }}"
                   hx-get="{{ route('contact-families.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#cf-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#cf-filters">
        </div>
        <div class="col-md-2">
            <select name="project_id" class="form-select form-select-sm"
                    hx-get="{{ route('contact-families.index') }}" hx-trigger="change"
                    hx-target="#cf-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#cf-filters">
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
                    hx-get="{{ route('contact-families.index') }}" hx-trigger="change"
                    hx-target="#cf-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#cf-filters">
                <option value="">All Programs</option>
                @foreach($filterPrograms ?? [] as $program)
                    <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>
                        {{ $program->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <x-table-filter-clear
            :href="route('contact-families.index')"
            :filter-keys="['search', 'project_id', 'program_id']"
        />
    </div>
    <input type="hidden" name="sort"      value="{{ $sort ?? 'sort_order' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>

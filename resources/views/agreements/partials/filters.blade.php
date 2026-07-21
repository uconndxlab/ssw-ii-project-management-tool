<form id="agreement-filters"
      data-table-filter-form
      hx-get="{{ route('agreements.index') }}"
      hx-target="#agreements-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search agreements…" value="{{ request('search') }}"
                   hx-get="{{ route('agreements.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#agreements-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#agreement-filters">
        </div>
        <div class="col-md-2">
            <select name="state_id" class="form-select form-select-sm"
                    hx-get="{{ route('agreements.index') }}" hx-trigger="change"
                    hx-target="#agreements-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#agreement-filters">
                <option value="">All States</option>
                @foreach($states as $state)
                    <option value="{{ $state->id }}" @selected((string) request('state_id') === (string) $state->id)>
                        {{ $state->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @if(auth()->user()->isAdmin())
            <div class="col-md-2">
                <select name="active" class="form-select form-select-sm"
                        hx-get="{{ route('agreements.index') }}" hx-trigger="change"
                        hx-target="#agreements-table" hx-swap="innerHTML"
                        hx-push-url="true" hx-include="#agreement-filters">
                    <option value="">All Agreements</option>
                    <option value="1" @selected(request('active') === '1')>Active Only</option>
                    <option value="0" @selected(request('active') === '0')>Inactive Only</option>
                </select>
            </div>
        @endif
        <div class="col-md-2">
            <select name="project_id" class="form-select form-select-sm"
                    hx-get="{{ route('agreements.index') }}" hx-trigger="change"
                    hx-target="#agreements-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#agreement-filters">
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
                    hx-get="{{ route('agreements.index') }}" hx-trigger="change"
                    hx-target="#agreements-table" hx-swap="innerHTML"
                    hx-push-url="true" hx-include="#agreement-filters">
                <option value="">All Programs</option>
                @foreach($filterPrograms ?? [] as $program)
                    <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>
                        {{ $program->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @php
            $agreementFilterKeys = ['search', 'state_id', 'project_id', 'program_id'];
            if (auth()->user()->isAdmin()) {
                $agreementFilterKeys[] = 'active';
            }
        @endphp
        <x-table-filter-clear
            :href="route('agreements.index')"
            :filter-keys="$agreementFilterKeys"
        />
    </div>
    <input type="hidden" name="sort"      value="{{ $sort ?? 'name' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>

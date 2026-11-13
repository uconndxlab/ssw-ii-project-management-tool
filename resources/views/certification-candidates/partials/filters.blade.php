<form id="candidate-filters"
      data-table-filter-form
      hx-get="{{ route('certification-candidates.index') }}"
      hx-trigger="change"
      hx-target="#candidates-table"
      hx-swap="innerHTML"
      hx-push-url="true">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <input aria-label="Search candidates" type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search name or email…" value="{{ request('search') }}"
                   hx-get="{{ route('certification-candidates.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="#candidates-table" hx-swap="innerHTML"
                   hx-push-url="true" hx-include="#candidate-filters">
        </div>
        <div class="col-md-3">
            <select aria-label="Filter by certificate" name="certificate_id" class="form-select form-select-sm">
                <option value="">All Certificates</option>
                @foreach($filterCertificates as $certificate)
                    <option value="{{ $certificate->id }}" @selected((string) request('certificate_id') === (string) $certificate->id)>{{ $certificate->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select aria-label="Filter by certification status" name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                @foreach(['assigned' => 'Assigned', 'in_progress' => 'In progress', 'endorsed' => 'Endorsed', 'certified' => 'Certified', 'expired' => 'Expired', 'revoked' => 'Revoked'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select aria-label="Filter by project" name="project_id" class="form-select form-select-sm">
                <option value="">All Projects</option>
                @foreach($filterProjects as $project)
                    <option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>{{ $project->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select aria-label="Filter by program" name="program_id" class="form-select form-select-sm">
                <option value="">All Programs</option>
                @foreach($filterPrograms as $program)
                    <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>{{ $program->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-3">
            <select aria-label="Filter by supervising coach" name="coach_id" class="form-select form-select-sm">
                <option value="">All Coaches</option>
                @foreach($filterCoaches as $coach)
                    <option value="{{ $coach->id }}" @selected((string) request('coach_id') === (string) $coach->id)>{{ $coach->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-auto">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="supervised" value="1" id="supervised" @checked(request()->boolean('supervised'))>
                <label class="form-check-label small" for="supervised">Supervised by me or my reports</label>
            </div>
        </div>
        <div class="col-auto">
            <div class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="include_inactive" value="1" id="include-inactive" @checked(request()->boolean('include_inactive'))>
                <label class="form-check-label small" for="include-inactive">Include inactive</label>
            </div>
        </div>
        <x-table-filter-clear
            :href="route('certification-candidates.index')"
            :filter-keys="['search', 'certificate_id', 'status', 'program_id', 'project_id', 'coach_id', 'supervised', 'include_inactive']"
        />
    </div>
    <input type="hidden" name="per_page" value="{{ \App\Support\TablePagination::perPage(request()) }}">
    <input type="hidden" name="sort" value="{{ $sort }}">
    <input type="hidden" name="direction" value="{{ $direction }}">
</form>

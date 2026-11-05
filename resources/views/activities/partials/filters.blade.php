@php
    $embedded = $embedded ?? false;
    $activityTarget = $embedded ? '#agreement-activities' : '#activities-table';
    $pushUrl = $embedded ? 'false' : 'true';
@endphp
<form id="activity-filters"
      data-table-filter-form
      hx-get="{{ route('activities.index') }}"
      hx-target="{{ $activityTarget }}"
      hx-swap="innerHTML"
      hx-push-url="{{ $pushUrl }}">
    <div class="row g-2 align-items-center">
        <div class="col-md-2">
            <input aria-label="Search activities" type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search activities…" value="{{ request('search') }}"
                   hx-get="{{ route('activities.index') }}"
                   hx-trigger="keyup changed delay:400ms, search"
                   hx-target="{{ $activityTarget }}" hx-swap="innerHTML"
                   hx-push-url="{{ $pushUrl }}" hx-include="#activity-filters">
        </div>
        <div class="col-md-2">
            <select aria-label="Filter by state" name="state_id" class="form-select form-select-sm"
                    hx-get="{{ route('activities.index') }}" hx-trigger="change"
                    hx-target="{{ $embedded ? $activityTarget : '#activity-filters-container' }}" hx-swap="innerHTML"
                    hx-push-url="{{ $pushUrl }}" hx-include="#activity-filters"
                    hx-vals='@json($embedded ? ["organization_id" => ""] : ["partial" => "filters", "organization_id" => "", "agreement_id" => ""])'>
                <option value="">All States</option>
                @foreach($states as $state)
                    <option value="{{ $state->id }}" @selected((string) request('state_id') === (string) $state->id)>
                        {{ $state->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select aria-label="Filter by organization" name="organization_id" class="form-select form-select-sm"
                    hx-get="{{ route('activities.index') }}" hx-trigger="change"
                    hx-target="{{ $embedded ? $activityTarget : '#activity-filters-container' }}" hx-swap="innerHTML"
                    hx-push-url="{{ $pushUrl }}" hx-include="#activity-filters"
                    @unless($embedded) hx-vals='{"partial":"filters","agreement_id":""}' @endunless>
                <option value="">All Organizations</option>
                @foreach($organizations as $organization)
                    <option value="{{ $organization->id }}" @selected((string) request('organization_id') === (string) $organization->id)>
                        {{ $organization->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @unless($embedded)
            <div class="col-md-2">
                <select aria-label="Filter by agreement" name="agreement_id" class="form-select form-select-sm"
                        hx-get="{{ route('activities.index') }}" hx-trigger="change"
                        hx-target="{{ $activityTarget }}" hx-swap="innerHTML"
                        hx-push-url="{{ $pushUrl }}" hx-include="#activity-filters">
                    <option value="">All Agreements</option>
                    @foreach($agreements as $agreement)
                        <option value="{{ $agreement->id }}" @selected((string) request('agreement_id') === (string) $agreement->id)>
                            {{ $agreement->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endunless
        <div class="col-md-2">
            <select aria-label="Filter by activity type" name="activity_type_id" class="form-select form-select-sm"
                    hx-get="{{ route('activities.index') }}" hx-trigger="change"
                    hx-target="{{ $activityTarget }}" hx-swap="innerHTML"
                    hx-push-url="{{ $pushUrl }}" hx-include="#activity-filters">
                <option value="">All Types</option>
                @foreach($activityTypes as $type)
                    <option value="{{ $type->id }}" @selected((string) request('activity_type_id') === (string) $type->id)>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select aria-label="Filter by certification review" name="needs_certification_review" class="form-select form-select-sm"
                hx-get="{{ route('activities.index') }}" hx-trigger="change" hx-target="{{ $activityTarget }}" hx-swap="innerHTML" hx-push-url="{{ $pushUrl }}" hx-include="#activity-filters">
                <option value="">All certification reviews</option>
                <option value="1" @selected(request('needs_certification_review') === '1')>Needs certification review</option>
            </select>
        </div>
        <div class="col-md-2">
            <select aria-label="Filter by project" name="project_id" class="form-select form-select-sm"
                    hx-get="{{ route('activities.index') }}" hx-trigger="change"
                    hx-target="{{ $activityTarget }}" hx-swap="innerHTML"
                    hx-push-url="{{ $pushUrl }}" hx-include="#activity-filters">
                <option value="">All Projects</option>
                @foreach($filterProjects as $project)
                    <option value="{{ $project->id }}" @selected((string) request('project_id') === (string) $project->id)>
                        {{ $project->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select aria-label="Filter by program" name="program_id" class="form-select form-select-sm"
                    hx-get="{{ route('activities.index') }}" hx-trigger="change"
                    hx-target="{{ $activityTarget }}" hx-swap="innerHTML"
                    hx-push-url="{{ $pushUrl }}" hx-include="#activity-filters">
                <option value="">All Programs</option>
                @foreach($filterPrograms as $program)
                    <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>
                        {{ $program->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @if($embedded)
            <div class="col-auto {{ request()->hasAny(['search', 'state_id', 'organization_id', 'activity_type_id', 'needs_certification_review', 'project_id', 'program_id']) ? '' : 'd-none' }}" data-table-filter-clear-wrap>
                <a class="btn btn-outline-secondary btn-sm" aria-label="Clear filters"
                   href="{{ route('activities.index', request()->only('agreement_id', 'embedded', 'per_page')) }}"
                   hx-get="{{ route('activities.index', request()->only('agreement_id', 'embedded', 'per_page')) }}"
                   hx-target="{{ $activityTarget }}" hx-push-url="false">Clear filters</a>
            </div>
        @else
            <x-table-filter-clear
                :href="route('activities.index')"
                :filter-keys="['project_id', 'program_id', 'search', 'state_id', 'organization_id', 'agreement_id', 'activity_type_id', 'needs_certification_review']"
            />
        @endif

    </div>
    @if($embedded)
        <input type="hidden" name="embedded" value="1">
        <input type="hidden" name="agreement_id" value="{{ request('agreement_id') }}">
    @endif
    <input type="hidden" name="per_page" value="{{ \App\Support\TablePagination::perPage(request()) }}">
    <input type="hidden" name="sort" value="{{ $sort ?? 'date' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'desc' }}">
</form>

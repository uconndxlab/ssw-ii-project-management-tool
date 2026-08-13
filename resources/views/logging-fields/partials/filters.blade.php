<form id="logging-field-filters"
      data-table-filter-form
      hx-get="{{ route('logging-fields.index') }}"
      hx-target="#logging-fields-table"
      hx-swap="innerHTML"
      hx-push-url="true">

    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Search fields…" value="{{ request('search') }}"
                   hx-get="{{ route('logging-fields.index') }}"
                   hx-trigger="keyup changed delay:400ms"
                   hx-target="#logging-fields-table"
                   hx-swap="innerHTML"
                   hx-push-url="true"
                   hx-include="#logging-field-filters">
        </div>
        <div class="col-md-2">
            <select name="project_id" class="form-select form-select-sm"
                    hx-get="{{ route('logging-fields.index') }}"
                    hx-trigger="change"
                    hx-target="#logging-fields-table"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    hx-include="#logging-field-filters">
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
                    hx-get="{{ route('logging-fields.index') }}"
                    hx-trigger="change"
                    hx-target="#logging-fields-table"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    hx-include="#logging-field-filters">
                <option value="">All Programs</option>
                @foreach($filterPrograms ?? [] as $program)
                    <option value="{{ $program->id }}" @selected((string) request('program_id') === (string) $program->id)>
                        {{ $program->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="contact_family_id" class="form-select form-select-sm"
                    hx-get="{{ route('logging-fields.index') }}"
                    hx-trigger="change"
                    hx-target="#logging-fields-table"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    hx-include="#logging-field-filters">
                <option value="">All Activity Families</option>
                @foreach($filterContactFamilies ?? [] as $contactFamily)
                    <option value="{{ $contactFamily->id }}" @selected((string) request('contact_family_id') === (string) $contactFamily->id)>
                        {{ $contactFamily->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select form-select-sm"
                    hx-get="{{ route('logging-fields.index') }}"
                    hx-trigger="change"
                    hx-target="#logging-fields-table"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    hx-include="#logging-field-filters">
                <option value="">All Statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="field_type" class="form-select form-select-sm"
                    hx-get="{{ route('logging-fields.index') }}"
                    hx-trigger="change"
                    hx-target="#logging-fields-table"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    hx-include="#logging-field-filters">
                <option value="">All Field Types</option>
                <option value="number" @selected(request('field_type') === 'number')>Number</option>
                <option value="decimal" @selected(request('field_type') === 'decimal')>Decimal</option>
                <option value="text" @selected(request('field_type') === 'text')>Text</option>
                <option value="textarea" @selected(request('field_type') === 'textarea')>Textarea</option>
                <option value="checkbox" @selected(request('field_type') === 'checkbox')>Checkbox</option>
                <option value="select" @selected(request('field_type') === 'select')>Select</option>
                <option value="document" @selected(request('field_type') === 'document')>Document Upload</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="availability" class="form-select form-select-sm"
                    hx-get="{{ route('logging-fields.index') }}"
                    hx-trigger="change"
                    hx-target="#logging-fields-table"
                    hx-swap="innerHTML"
                    hx-push-url="true"
                    hx-include="#logging-field-filters">
                <option value="">All Availability</option>
                <option value="available_in_agreements" @selected(request('availability') === 'available_in_agreements')>Agreements</option>
                <option value="available_in_contact_families" @selected(request('availability') === 'available_in_contact_families')>Activity Families</option>
                <option value="available_in_activities" @selected(request('availability') === 'available_in_activities')>Activity Types</option>
            </select>
        </div>
        <x-table-filter-clear
            :href="route('logging-fields.index')"
            :filter-keys="['search', 'status', 'field_type', 'availability', 'project_id', 'program_id', 'contact_family_id']"
        />
    </div>

    <input type="hidden" name="sort" value="{{ $sort ?? 'sort_order' }}">
    <input type="hidden" name="direction" value="{{ $direction ?? 'asc' }}">
</form>

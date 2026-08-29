@php
    $actorIsSystemAdmin = $actor->access()->isSystemAdmin();
    $entries = old('privilege_entries');
    if (! is_array($entries)) {
        $entries = $user->privileges
            ->filter(fn ($p) => $p->scope_type->value !== 'system')
            ->map(fn ($p) => [
                'scope_type' => $p->scope_type->value,
                'scope_id' => $p->scope_id,
                'admin' => $p->capability->value === 'admin',
            ])
            ->values()
            ->all();
    }
    $projectNames = $ledgerProjects->mapWithKeys(fn ($p) => [$p->id => $p->name]);
    $programNames = $ledgerPrograms->mapWithKeys(fn ($p) => [$p->id => $p->name]);
    $selectedPrivilegeProjectIds = collect($entries)->where('scope_type', 'project')->pluck('scope_id')->all();
    $selectedPrivilegeProgramIds = collect($entries)->where('scope_type', 'program')->pluck('scope_id')->all();
    $projectBadgeClass = \App\Support\EntityBadge::relationClasses('project');
    $programBadgeClass = \App\Support\EntityBadge::relationClasses('program');
@endphp

<div data-privilege-ledger>
    @if($actorIsSystemAdmin)
        <div class="mb-3">
            <div class="form-label">Coverage</div>
            <div class="d-flex flex-wrap gap-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="privilege-coverage-system" name="privilege_coverage" value="system" {{ $coverage === 'system' ? 'checked' : '' }}>
                    <label class="form-check-label" for="privilege-coverage-system">Entire system</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" id="privilege-coverage-specific" name="privilege_coverage" value="specific" {{ $coverage !== 'system' ? 'checked' : '' }}>
                    <label class="form-check-label" for="privilege-coverage-specific">Specific</label>
                </div>
            </div>
        </div>
    @else
        <input type="hidden" name="privilege_coverage" value="specific">
    @endif

    <div class="row g-4 g-lg-5 align-items-stretch">
        <div class="col-lg-5 d-flex">
            <div class="d-flex flex-column w-100 h-100">
                <div class="d-grid gap-4" data-specific-ledger>
                    <x-form-field label="Projects" class="mb-0">
                        <x-token-picker
                            picker-id="privilege-projects"
                            name="privilege_pick_project_ids[]"
                            :items="$ledgerProjects"
                            :selected-ids="$selectedPrivilegeProjectIds"
                            placeholder="Search projects..."
                            :open-on-focus="false"
                            :show-selected="false"
                            :height="'220px'"
                            entity="project"
                        />
                    </x-form-field>

                    <x-form-field label="Programs" class="mb-0">
                        <x-token-picker
                            picker-id="privilege-programs"
                            name="privilege_pick_program_ids[]"
                            :items="$ledgerPrograms"
                            :selected-ids="$selectedPrivilegeProgramIds"
                            placeholder="Search programs..."
                            :open-on-focus="false"
                            :show-selected="false"
                            :height="'220px'"
                            entity="program"
                        />
                    </x-form-field>
                </div>

                <div class="border rounded px-3 py-3 mt-3 mt-lg-auto bg-body-tertiary">
                    <div class="fw-semibold small mb-2">Admin / Enhanced Viewer</div>
                    <div class="small text-muted" data-privilege-preview></div>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="border rounded overflow-hidden d-flex flex-column" style="max-height: 420px; background-color: #e9ecef;">
                <div class="small text-muted px-3 py-2 border-bottom bg-body flex-shrink-0">
                    Access
                </div>
                <div class="flex-grow-1 overflow-auto" style="min-height: 0;">
                    <div class="m-3 mt-2 mb-2">
                        <div class="d-grid gap-2" data-privilege-rows>
                            <div class="border rounded overflow-hidden bg-body {{ $coverage === 'system' ? '' : 'd-none' }}" data-system-access-row>
                                <div class="d-flex justify-content-between align-items-center gap-2 px-3 py-2">
                                    <span class="fw-semibold small">Entire system</span>
                                    <label class="form-check form-switch m-0 d-inline-flex align-items-center gap-2" data-system-admin-switch>
                                        <input type="hidden" name="privilege_system_admin" value="0">
                                        <input class="form-check-input mt-0" type="checkbox" name="privilege_system_admin" value="1" {{ $systemIsAdmin ? 'checked' : '' }} aria-label="System admin">
                                        <span class="small text-muted">Admin</span>
                                    </label>
                                </div>
                            </div>

                            @foreach($entries as $index => $entry)
                                @php
                                    $isProject = ($entry['scope_type'] ?? '') === 'project';
                                    $label = $isProject
                                        ? ($projectNames[$entry['scope_id']] ?? $entry['scope_id'])
                                        : ($programNames[$entry['scope_id']] ?? $entry['scope_id']);
                                    $badgeClass = $isProject ? $projectBadgeClass : $programBadgeClass;
                                @endphp
                                <div class="border rounded overflow-hidden bg-body"
                                     data-privilege-row
                                     data-scope-type="{{ $entry['scope_type'] }}"
                                     data-scope-id="{{ $entry['scope_id'] }}">
                                    <div class="d-flex justify-content-between align-items-start gap-2 px-3 py-2">
                                        <div class="min-w-0">
                                            <span class="badge {{ $badgeClass }}">{{ $label }}</span>
                                            @if($isProject)
                                                <div class="small text-muted mt-1">Permissions scoped to all programs within the project.</div>
                                            @endif
                                        </div>
                                        <div class="d-flex align-items-center gap-3">
                                            <input type="hidden" name="privilege_entries[{{ $index }}][scope_type]" value="{{ $entry['scope_type'] }}">
                                            <input type="hidden" name="privilege_entries[{{ $index }}][scope_id]" value="{{ $entry['scope_id'] }}">
                                            <label class="form-check form-switch m-0 d-inline-flex align-items-center gap-2">
                                                <input type="hidden" name="privilege_entries[{{ $index }}][admin]" value="0">
                                                <input class="form-check-input mt-0" type="checkbox" name="privilege_entries[{{ $index }}][admin]" value="1" {{ ! empty($entry['admin']) ? 'checked' : '' }} aria-label="Admin">
                                                <span class="small text-muted">Admin</span>
                                            </label>
                                            <button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0 lh-1 fs-4" data-remove-privilege aria-label="Remove">&times;</button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="text-muted small py-3 {{ count($entries) > 0 || $coverage === 'system' ? 'd-none' : '' }}" data-privilege-empty>
                            No projects or programs have been added yet.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @error('privilege_entries')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
    @error('privilege_coverage')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const root = document.querySelector('[data-privilege-ledger]');
        if (!root) return;

        const rows = root.querySelector('[data-privilege-rows]');
        const empty = root.querySelector('[data-privilege-empty]');
        const specific = root.querySelector('[data-specific-ledger]');
        const systemRow = root.querySelector('[data-system-access-row]');
        const preview = root.querySelector('[data-privilege-preview]');
        const projectPicker = document.getElementById('privilege-projects');
        const programPicker = document.getElementById('privilege-programs');
        const projectNames = @json($projectNames);
        const programNames = @json($programNames);
        const projectBadgeClass = @json($projectBadgeClass);
        const programBadgeClass = @json($programBadgeClass);
        let nextIndex = {{ count($entries) }};

        function coverage() {
            return root.querySelector('input[name="privilege_coverage"]:checked')?.value
                || root.querySelector('input[name="privilege_coverage"]')?.value
                || 'specific';
        }

        function pickerIds(picker) {
            if (!picker) return [];
            return Array.from(picker.querySelectorAll('[data-token-inputs] input[type="hidden"]')).map(function (input) {
                return String(input.value);
            });
        }

        function setPickerIds(picker, values) {
            if (!picker) return;
            picker.dispatchEvent(new CustomEvent('token-picker:set', {
                detail: values,
                bubbles: true,
            }));
        }

        function rowKey(type, id) {
            return String(type) + ':' + String(id);
        }

        function existingKeys() {
            const keys = new Set();
            rows.querySelectorAll('[data-privilege-row]').forEach(function (row) {
                keys.add(rowKey(row.dataset.scopeType, row.dataset.scopeId));
            });
            return keys;
        }

        function updatePreview() {
            if (!preview) return;
            if (coverage() === 'system') {
                const admin = root.querySelector('input[name="privilege_system_admin"][type="checkbox"]')?.checked;
                preview.textContent = admin
                    ? 'Can edit and view all records system-wide.'
                    : 'Can view all records system-wide; edit only activities they are on.';
                return;
            }
            const labels = [];
            rows.querySelectorAll('[data-privilege-row]').forEach(function (row) {
                const type = row.dataset.scopeType;
                const id = row.dataset.scopeId;
                const admin = row.querySelector('[name*="[admin]"][type="checkbox"]')?.checked;
                const name = type === 'project' ? projectNames[id] : programNames[id];
                labels.push((admin ? 'Admin of ' : 'Viewer of ') + (name || id));
            });
            preview.textContent = labels.length
                ? labels.join(' · ')
                : 'Add projects or programs to define this role\'s access.';
        }

        function updateEmpty() {
            const isSystem = coverage() === 'system';
            const hasRows = rows.querySelectorAll('[data-privilege-row]').length > 0;
            if (empty) empty.classList.toggle('d-none', isSystem || hasRows);
            updatePreview();
        }

        function syncCoverage() {
            const isSystem = coverage() === 'system';
            if (specific) specific.classList.toggle('d-none', isSystem);
            if (systemRow) systemRow.classList.toggle('d-none', !isSystem);
            rows.querySelectorAll('[data-privilege-row]').forEach(function (row) {
                row.classList.toggle('d-none', isSystem);
            });
            updateEmpty();
        }

        function addRow(type, id) {
            if (!id) return;
            const key = rowKey(type, id);
            if (existingKeys().has(key)) return;

            const name = type === 'project' ? projectNames[id] : programNames[id];
            const badgeClass = type === 'project' ? projectBadgeClass : programBadgeClass;
            const index = nextIndex++;
            const card = document.createElement('div');
            card.className = 'border rounded overflow-hidden bg-body';
            card.dataset.privilegeRow = '';
            card.dataset.scopeType = type;
            card.dataset.scopeId = String(id);
            card.innerHTML = '<div class="d-flex justify-content-between align-items-start gap-2 px-3 py-2">'
                + '<div class="min-w-0">'
                + '<span class="badge ' + badgeClass + '"></span>'
                + (type === 'project'
                    ? '<div class="small text-muted mt-1">Permissions scoped to all programs within the project.</div>'
                    : '')
                + '</div>'
                + '<div class="d-flex align-items-center gap-3">'
                + '<input type="hidden" name="privilege_entries[' + index + '][scope_type]" value="' + type + '">'
                + '<input type="hidden" name="privilege_entries[' + index + '][scope_id]" value="' + id + '">'
                + '<label class="form-check form-switch m-0 d-inline-flex align-items-center gap-2">'
                + '<input type="hidden" name="privilege_entries[' + index + '][admin]" value="0">'
                + '<input class="form-check-input mt-0" type="checkbox" name="privilege_entries[' + index + '][admin]" value="1" aria-label="Admin">'
                + '<span class="small text-muted">Admin</span>'
                + '</label>'
                + '<button type="button" class="btn btn-sm btn-link text-danger text-decoration-none p-0 lh-1 fs-4" data-remove-privilege aria-label="Remove">&times;</button>'
                + '</div>'
                + '</div>';
            card.querySelector('.badge').textContent = name || id;
            rows.appendChild(card);
            updateEmpty();
        }

        function syncFromPickers() {
            const wanted = [];
            pickerIds(projectPicker).forEach(function (id) {
                wanted.push({ type: 'project', id: id });
            });
            pickerIds(programPicker).forEach(function (id) {
                wanted.push({ type: 'program', id: id });
            });
            const wantedKeys = new Set(wanted.map(function (item) {
                return rowKey(item.type, item.id);
            }));

            rows.querySelectorAll('[data-privilege-row]').forEach(function (row) {
                if (!wantedKeys.has(rowKey(row.dataset.scopeType, row.dataset.scopeId))) {
                    row.remove();
                }
            });

            wanted.forEach(function (item) {
                addRow(item.type, item.id);
            });

            updateEmpty();
        }

        projectPicker?.addEventListener('token-picker:change', syncFromPickers);
        programPicker?.addEventListener('token-picker:change', syncFromPickers);

        rows.addEventListener('click', function (event) {
            const button = event.target.closest('[data-remove-privilege]');
            if (!button) return;
            const row = button.closest('[data-privilege-row]');
            if (!row) return;
            const type = row.dataset.scopeType;
            const id = String(row.dataset.scopeId);
            const picker = type === 'project' ? projectPicker : programPicker;
            setPickerIds(picker, pickerIds(picker).filter(function (value) {
                return value !== id;
            }));
        });
        rows.addEventListener('change', updatePreview);
        root.querySelector('input[name="privilege_system_admin"][type="checkbox"]')?.addEventListener('change', updatePreview);
        root.querySelectorAll('input[name="privilege_coverage"]').forEach(function (input) {
            input.addEventListener('change', syncCoverage);
        });

        syncCoverage();
    });
</script>

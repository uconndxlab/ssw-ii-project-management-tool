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
        <fieldset class="mb-3">
            <legend class="form-label float-none w-auto p-0 fs-6">Coverage</legend>
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
        </fieldset>
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
                            placeholder="Search projects…"
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
                            placeholder="Search programs…"
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
            <div class="border rounded overflow-hidden d-flex flex-column" style="max-height: 420px; background-color: var(--app-surface-fill);">
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

    @php
        $privilegeLedgerConfig = [
            'projectNames' => $projectNames,
            'programNames' => $programNames,
            'projectBadgeClass' => $projectBadgeClass,
            'programBadgeClass' => $programBadgeClass,
            'nextIndex' => count($entries),
        ];
    @endphp
    <script type="application/json" data-privilege-ledger-config>@json($privilegeLedgerConfig)</script>
</div>

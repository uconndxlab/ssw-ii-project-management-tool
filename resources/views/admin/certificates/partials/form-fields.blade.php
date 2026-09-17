@php
    $isEditMode = isset($certificate);
    $selectedProjectIds = old('project_ids', $isEditMode ? $certificate->projects->pluck('id')->toArray() : []);
    $selectedProgramIds = old('program_ids', $isEditMode ? $certificate->programs->pluck('id')->toArray() : []);
    $scopeId = $isEditMode ? 'certificate-edit-scope' : 'certificate-create-scope';

    $selectedPrerequisiteIds = old('prerequisite_certificate_ids', $isEditMode ? $certificate->prerequisites->pluck('id')->toArray() : []);

    $roleRows = old('roles', $isEditMode ? $certificate->roles->map(fn ($r) => [
        'id' => $r->id,
        'name' => $r->name,
        'active' => $r->active,
    ])->all() : []);

    if ($isEditMode) {
        $groupIdToIndex = [];
        $allGroupRows = [];
        foreach ($certificate->requirementGroups as $i => $group) {
            $allGroupRows[$i] = [
                'id' => $group->id,
                'phase' => $group->phase?->value ?? $group->phase,
                'label' => $group->label,
                'satisfy_mode' => $group->satisfy_mode?->value ?? $group->satisfy_mode,
                'required_count' => $group->required_count,
            ];
            $groupIdToIndex[$group->id] = $i;
        }

        $allRequirementRows = [];
        foreach ($certificate->requirements as $i => $requirement) {
            $allRequirementRows[$i] = [
                'id' => $requirement->id,
                'phase' => $requirement->phase?->value ?? $requirement->phase,
                'group_index' => $requirement->certificate_requirement_group_id ? ($groupIdToIndex[$requirement->certificate_requirement_group_id] ?? '') : '',
                'kind' => $requirement->kind?->value ?? $requirement->kind,
                'contact_family_id' => $requirement->contact_family_id,
                'activity_type_id' => $requirement->activity_type_id,
                'certification_tool_id' => $requirement->certification_tool_id,
                'certification_role_id' => $requirement->certification_role_id,
                'target_count' => $requirement->target_count,
                'requires_passing' => $requirement->requires_passing,
                'threshold_note' => $requirement->threshold_note,
                'window_months' => $requirement->window_months,
                'label' => $requirement->label,
                'notes' => $requirement->notes,
                'dimension_rules' => $requirement->dimensionRules->map(fn ($rule) => [
                    'id' => $rule->id,
                    'certification_tool_dimension_id' => $rule->certification_tool_dimension_id,
                    'mode' => $rule->mode?->value ?? $rule->mode,
                    'option_ids' => $rule->option_ids ?? [],
                    'min_count' => $rule->min_count,
                ])->all(),
            ];
        }
    } else {
        $allGroupRows = [];
        $allRequirementRows = [];
    }

    $allGroupRows = old('requirement_groups', $allGroupRows);
    $allRequirementRows = old('requirements', $allRequirementRows);

    $initialGroupRows = collect($allGroupRows)->filter(fn ($g) => ($g['phase'] ?? 'initial') === 'initial');
    $renewalGroupRows = collect($allGroupRows)->filter(fn ($g) => ($g['phase'] ?? 'initial') === 'renewal');
    $initialRequirementRows = collect($allRequirementRows)->filter(fn ($r) => ($r['phase'] ?? 'initial') === 'initial');
    $renewalRequirementRows = collect($allRequirementRows)->filter(fn ($r) => ($r['phase'] ?? 'initial') === 'renewal');

    $nextGroupIndex = empty($allGroupRows) ? 0 : max(array_keys($allGroupRows)) + 1;
    $nextRequirementIndex = empty($allRequirementRows) ? 0 : max(array_keys($allRequirementRows)) + 1;

    $initialGroupLabels = $initialGroupRows->map(fn ($g) => $g['label'] ?? 'Group');
    $renewalGroupLabels = $renewalGroupRows->map(fn ($g) => $g['label'] ?? 'Group');

    $roleOptions = ($globalRoles ?? collect())->merge($isEditMode ? $certificate->roles : collect());
@endphp

<x-section-card title="Information">
    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text" class="form-control @error('name') is-invalid @enderror"
               id="name" name="name" value="{{ old('name', $certificate->name ?? '') }}" required>
    </x-form-field>

    <x-form-field label="Description" for="description" name="description">
        <textarea class="form-control @error('description') is-invalid @enderror"
                  id="description" name="description" rows="3">{{ old('description', $certificate->description ?? '') }}</textarea>
    </x-form-field>

    <x-project-program-scope-picker
        :scope-id="$scopeId"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $certificate->program_scope_mode?->value ?? 'specific')"
        :lock-all="$isEditMode && $certificate->program_scope_mode?->value === 'all'"
        project-empty-selection-label="All projects"
        program-empty-selection-label="All programs"
    />

    <div class="row g-3 mt-1">
        <div class="col-md-4">
            <x-form-field label="Valid for (months)" for="validity_months" name="validity_months" help="Leave blank if it never expires.">
                <input type="number" class="form-control @error('validity_months') is-invalid @enderror"
                       id="validity_months" name="validity_months" min="1"
                       value="{{ old('validity_months', $certificate->validity_months ?? '') }}">
            </x-form-field>
        </div>
        <div class="col-md-4">
            <x-form-field label="Default rolling window (months)" for="default_window_months" name="default_window_months" help="Leave blank to count all-time. Individual requirements may override.">
                <input type="number" class="form-control @error('default_window_months') is-invalid @enderror"
                       id="default_window_months" name="default_window_months" min="1"
                       value="{{ old('default_window_months', $certificate->default_window_months ?? '') }}">
            </x-form-field>
        </div>
        <div class="col-md-4">
            <x-form-field label="Prerequisites satisfied when" for="prerequisite_mode" name="prerequisite_mode">
                <select class="form-select @error('prerequisite_mode') is-invalid @enderror" id="prerequisite_mode" name="prerequisite_mode">
                    <option value="all" @selected(old('prerequisite_mode', $certificate->prerequisite_mode ?? 'all') === 'all')>All selected</option>
                    <option value="any" @selected(old('prerequisite_mode', $certificate->prerequisite_mode ?? 'all') === 'any')>Any selected</option>
                </select>
            </x-form-field>
        </div>
    </div>

    <x-form-field label="Prerequisite certificates" name="prerequisite_certificate_ids" help="A candidate must already hold these (per the mode above) before pursuing this certificate.">
        <x-token-picker
            picker-id="certificate-prerequisites"
            name="prerequisite_certificate_ids[]"
            :items="$otherCertificates"
            :selected-ids="$selectedPrerequisiteIds"
            placeholder="Search certificates..."
        />
    </x-form-field>

    <x-form-options class="mt-4">
        <x-form-switch
            name="active"
            label="Active"
            help="Only active certificates can be pursued by new enrollments."
            :checked="old('active', $isEditMode ? $certificate->active : true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<x-section-card title="Certificate-specific Roles" class="mt-3">
    <p class="text-muted small mb-2">Optional. Roles that only apply to this certificate's requirements (e.g. Trainer's Attend/Observe/Co-Train/Be Observed). Standard roles are managed separately.</p>
    <x-repeater-rows
        name="roles"
        :rows="$roleRows"
        add-label="Add role"
        :template="view('admin.certificates.partials.role-row', ['row' => [], 'roleIndex' => '__INDEX__'])->render()"
    >
        @foreach($roleRows as $roleIndex => $role)
            @include('admin.certificates.partials.role-row', ['row' => $role, 'roleIndex' => $roleIndex])
        @endforeach
    </x-repeater-rows>
</x-section-card>

<x-section-card title="Requirements" class="mt-3">
    <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#requirements-initial" type="button" role="tab">Initial Certification</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#requirements-renewal" type="button" role="tab">Recertification</button>
        </li>
    </ul>
    <div class="tab-content border border-top-0 rounded-bottom p-3">
        <div class="tab-pane fade show active" id="requirements-initial" role="tabpanel">
            <h6 class="text-muted small text-uppercase mt-1">Requirement Groups <small class="fw-normal">(optional; for "complete N of these" patterns)</small></h6>
            <x-repeater-rows
                name="requirement_groups"
                :rows="$initialGroupRows->all()"
                :next-index="$nextGroupIndex"
                add-label="Add group"
                :template="view('admin.certificates.partials.requirement-group-row', ['row' => [], 'groupIndex' => '__INDEX__', 'phase' => 'initial'])->render()"
            >
                @foreach($initialGroupRows as $groupIndex => $group)
                    @include('admin.certificates.partials.requirement-group-row', ['row' => $group, 'groupIndex' => $groupIndex, 'phase' => 'initial'])
                @endforeach
            </x-repeater-rows>

            <h6 class="text-muted small text-uppercase mt-4">Requirements</h6>
            <x-repeater-rows
                name="requirements"
                :rows="$initialRequirementRows->all()"
                :next-index="$nextRequirementIndex"
                add-label="Add requirement"
                :template="view('admin.certificates.partials.requirement-row', [
                    'row' => [], 'reqIndex' => '__INDEX__', 'phase' => 'initial',
                    'groupOptions' => $initialGroupLabels, 'roleOptions' => $roleOptions,
                    'contactFamilies' => $contactFamilies, 'activityTypes' => $activityTypes, 'certificationTools' => $certificationTools,
                ])->render()"
            >
                @foreach($initialRequirementRows as $reqIndex => $requirement)
                    @include('admin.certificates.partials.requirement-row', [
                        'row' => $requirement, 'reqIndex' => $reqIndex, 'phase' => 'initial',
                        'groupOptions' => $initialGroupLabels, 'roleOptions' => $roleOptions,
                        'contactFamilies' => $contactFamilies, 'activityTypes' => $activityTypes, 'certificationTools' => $certificationTools,
                    ])
                @endforeach
            </x-repeater-rows>
        </div>

        <div class="tab-pane fade" id="requirements-renewal" role="tabpanel">
            <h6 class="text-muted small text-uppercase mt-1">Requirement Groups <small class="fw-normal">(optional)</small></h6>
            <x-repeater-rows
                name="requirement_groups"
                :rows="$renewalGroupRows->all()"
                :next-index="$nextGroupIndex + 1000"
                add-label="Add group"
                :template="view('admin.certificates.partials.requirement-group-row', ['row' => [], 'groupIndex' => '__INDEX__', 'phase' => 'renewal'])->render()"
            >
                @foreach($renewalGroupRows as $groupIndex => $group)
                    @include('admin.certificates.partials.requirement-group-row', ['row' => $group, 'groupIndex' => $groupIndex, 'phase' => 'renewal'])
                @endforeach
            </x-repeater-rows>

            <h6 class="text-muted small text-uppercase mt-4">Requirements</h6>
            <x-repeater-rows
                name="requirements"
                :rows="$renewalRequirementRows->all()"
                :next-index="$nextRequirementIndex + 1000"
                add-label="Add requirement"
                :template="view('admin.certificates.partials.requirement-row', [
                    'row' => [], 'reqIndex' => '__INDEX__', 'phase' => 'renewal',
                    'groupOptions' => $renewalGroupLabels, 'roleOptions' => $roleOptions,
                    'contactFamilies' => $contactFamilies, 'activityTypes' => $activityTypes, 'certificationTools' => $certificationTools,
                ])->render()"
            >
                @foreach($renewalRequirementRows as $reqIndex => $requirement)
                    @include('admin.certificates.partials.requirement-row', [
                        'row' => $requirement, 'reqIndex' => $reqIndex, 'phase' => 'renewal',
                        'groupOptions' => $renewalGroupLabels, 'roleOptions' => $roleOptions,
                        'contactFamilies' => $contactFamilies, 'activityTypes' => $activityTypes, 'certificationTools' => $certificationTools,
                    ])
                @endforeach
            </x-repeater-rows>
        </div>
    </div>
</x-section-card>

@once
<script>
(function () {
    // Keeps requirement/dimension-rule field visibility in sync with kind/mode/dimension selects,
    // for both server-rendered rows and rows added later by the generic repeater component.
    function syncRequirementRow(row) {
        const kindSelect = row.querySelector('[data-kind-select]');
        if (!kindSelect) return;
        row.querySelectorAll('.kind-field').forEach(function (field) {
            field.style.display = field.getAttribute('data-kind-field') === kindSelect.value ? '' : 'none';
        });
    }

    function syncDimensionRuleRow(row) {
        const modeSelect = row.querySelector('[data-mode-select]');
        const minCountGroup = row.querySelector('[data-min-count-group]');
        if (modeSelect && minCountGroup) {
            minCountGroup.style.display = modeSelect.value === 'quota' ? '' : 'none';
        }

        const dimensionSelect = row.querySelector('[data-dimension-select]');
        if (dimensionSelect) {
            row.querySelectorAll('[data-option-group]').forEach(function (group) {
                group.classList.toggle('d-none', group.getAttribute('data-dimension-id') !== dimensionSelect.value);
            });
        }
    }

    function syncGroupRow(row) {
        const satisfySelect = row.querySelector('[data-group-satisfy-select]');
        const requiredCountWrap = row.querySelector('[data-group-required-count-wrap]');
        if (satisfySelect && requiredCountWrap) {
            requiredCountWrap.style.display = satisfySelect.value === 'n_of' ? '' : 'none';
        }
    }

    function syncAll(root) {
        root.querySelectorAll('[data-requirement-row]').forEach(syncRequirementRow);
        root.querySelectorAll('[data-dimension-rule-row]').forEach(syncDimensionRuleRow);
        root.querySelectorAll('[data-repeater-row][data-group-satisfy-select], [data-repeater-row]').forEach(function (row) {
            if (row.querySelector('[data-group-satisfy-select]')) syncGroupRow(row);
        });
    }

    document.addEventListener('change', function (e) {
        if (e.target.matches('[data-kind-select]')) {
            syncRequirementRow(e.target.closest('[data-requirement-row]'));
        }
        if (e.target.matches('[data-mode-select], [data-dimension-select]')) {
            syncDimensionRuleRow(e.target.closest('[data-dimension-rule-row]'));
        }
        if (e.target.matches('[data-group-satisfy-select]')) {
            syncGroupRow(e.target.closest('[data-repeater-row]'));
        }
    });

    const observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (mutation) {
            mutation.addedNodes.forEach(function (node) {
                if (node.nodeType !== 1) return;
                syncAll(node.matches('[data-repeater-row]') ? node.parentElement : node);
            });
        });
    });
    observer.observe(document.body, {childList: true, subtree: true});

    syncAll(document);
})();
</script>
@endonce

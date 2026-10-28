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


    $initialGroupLabels = $initialGroupRows->map(fn ($g) => $g['label'] ?? 'Group');
    $renewalGroupLabels = $renewalGroupRows->map(fn ($g) => $g['label'] ?? 'Group');


    $renewalMatchesInitial = filter_var(old('renewal_matches_initial', $isEditMode ? $certificate->renewal_matches_initial : false), FILTER_VALIDATE_BOOLEAN);
@endphp

<div data-certificate-editor data-errors='@json($errors->keys())'>
    <nav class="nav nav-pills gap-4 mb-3 cert-sections" aria-label="Certificate sections">
        @foreach(['basics' => 'Basics', 'initial' => 'Initial requirements', 'renewal' => 'Renewal', 'review' => 'Review rules'] as $key => $label)
            <button type="button" class="nav-link {{ $key === 'basics' ? 'active' : '' }}" data-cert-nav="{{ $key }}" aria-controls="cert-section-{{ $key }}" @if($key === 'basics') aria-current="page" @endif>{{ $label }}</button>
        @endforeach
    </nav>
    <p class="text-muted small">Move between sections freely. All changes are saved together with the certificate.</p>
    <div id="cert-section-basics" data-cert-section="basics">
<x-section-card title="Basics">
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
            <x-form-field label="Award validity (months)" for="validity_months" name="validity_months" help="Leave blank if it never expires.">
                <input type="number" class="form-control @error('validity_months') is-invalid @enderror"
                       id="validity_months" name="validity_months" min="1"
                       value="{{ old('validity_months', $certificate->validity_months ?? '') }}">
            </x-form-field>
        </div>
        <div class="col-md-4">
            <x-form-field label="Default work lookback (months)" for="default_window_months" name="default_window_months" help="How far back eligible work can count. Blank means all time; requirements can override it.">
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

    </div>
    @foreach(['initial' => 'Initial requirements', 'renewal' => 'Renewal'] as $phase => $phaseLabel)
        <div id="cert-section-{{ $phase }}" data-cert-section="{{ $phase }}" hidden>
            <x-section-card :title="$phaseLabel" :subtitle="$phase === 'initial' ? 'Every standalone requirement and every group must be satisfied.' : 'Configure renewal rules here. Renewal processing comes in a later release.'">
                @if($phase === 'renewal')
                    <x-form-switch name="renewal_matches_initial" label="Follow initial requirements" help="Linked: changes to initial requirements also change the renewal rules." :checked="$renewalMatchesInitial" />
                    <p class="text-muted" data-cert-linked-note @if(!$renewalMatchesInitial) hidden @endif>Renewal uses the initial requirements above, including their groups, filters and counting windows.</p>
                    <div class="d-flex flex-wrap gap-2 mb-3" data-cert-renewal-actions @if($renewalMatchesInitial) hidden @endif>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-cert-copy>Copy initial as independent rules</button>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-cert-clear>Clear renewal rules</button>
                    </div>
                @endif
                <div data-cert-stage="{{ $phase }}" @if($phase === 'renewal' && $renewalMatchesInitial) hidden @endif>
                    <div data-cert-ungrouped>
                        @foreach($allRequirementRows as $reqIndex => $row)
                            @if(($row['phase'] ?? 'initial') === $phase)
                                @include('admin.certificates.partials.requirement-row', ['groupOptions' => $phase === 'initial' ? $initialGroupLabels : $renewalGroupLabels])
                            @endif
                        @endforeach
                    </div>
                    <div data-cert-groups>
                        @foreach($allGroupRows as $groupIndex => $row)
                            @if(($row['phase'] ?? 'initial') === $phase)
                                @include('admin.certificates.partials.requirement-group-row')
                            @endif
                        @endforeach
                    </div>
                    <p class="text-muted py-3 mb-0" data-cert-empty>No requirements yet. Add the work or confirmation this certificate needs.</p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <button type="button" class="btn btn-outline-primary" data-cert-add="requirement">Add requirement</button>
                        <button type="button" class="btn btn-outline-primary" data-cert-add="group">Add requirement group</button>
                    </div>
                </div>
            </x-section-card>
        </div>
    @endforeach
    <div id="cert-section-review" data-cert-section="review" hidden>
        <x-section-card title="Review rules" subtitle="Review the configuration before saving. These are rules, not candidate progress.">
            <div data-cert-review></div>
        </x-section-card>
    </div>
    <div class="border rounded p-3 my-3 bg-white" data-cert-roles hidden>
        <h3 class="h6">Certificate-specific roles</h3>
        <p class="text-muted small">Available only on this certificate. Global roles stay available in each role selector. Changes here are saved with the certificate.</p>
        <div data-cert-role-rows>
            @foreach($roleRows as $roleIndex => $row)
                @include('admin.certificates.partials.role-row')
            @endforeach
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-primary btn-sm" data-cert-add="role">Add role</button>
            <button type="button" class="btn btn-outline-secondary btn-sm" data-cert-close-roles>Done editing roles</button>
        </div>
    </div>
    <div class="visually-hidden" role="status" aria-live="polite" data-cert-status></div>
    <template data-cert-template="requirement">@include('admin.certificates.partials.requirement-row', ['row' => [], 'reqIndex' => '__REQ__', 'phase' => '__PHASE__', 'groupOptions' => []])</template>
    <template data-cert-template="group">@include('admin.certificates.partials.requirement-group-row', ['row' => [], 'groupIndex' => '__GROUP__', 'phase' => '__PHASE__'])</template>
    <template data-cert-template="role">@include('admin.certificates.partials.role-row', ['row' => [], 'roleIndex' => '__ROLE__'])</template>
</div>

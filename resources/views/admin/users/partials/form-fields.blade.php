@php
    $isEdit = $user->exists;
    $selectedProjectIds = old('project_ids', $user->projects?->pluck('id')->toArray() ?? []);
    $selectedProgramIds = old('program_ids', $user->programs?->pluck('id')->toArray() ?? []);
    $selectedProfile = old('access_profile', $user->access_profile?->value ?? 'member');
    $user->loadMissing('privileges');
    $hasSystemPrivilege = $user->privileges->contains(fn ($p) => $p->scope_type->value === 'system');
    $systemIsAdmin = $user->privileges->contains(fn ($p) => $p->scope_type->value === 'system' && $p->capability->value === 'admin');
    $coverage = old('privilege_coverage', $hasSystemPrivilege ? 'system' : 'specific');
    $actor = auth()->user();
@endphp

<x-section-card title="Information">
    <x-form-field label="Name" for="name" name="name" :required="true">
        <input type="text"
               class="form-control @error('name') is-invalid @enderror"
               id="name"
               name="name"
               value="{{ old('name', $user->name) }}"
               required>
    </x-form-field>

    <x-form-field label="Email" for="email" name="email" :required="true">
        <input type="email"
               class="form-control @error('email') is-invalid @enderror"
               id="email"
               name="email"
               value="{{ old('email', $user->email) }}"
               required>
    </x-form-field>

    <x-form-field
        label="Password"
        for="password"
        name="password"
        :required="! $isEdit"
        :help="$isEdit ? 'Leave blank to keep the current password.' : null"
    >
        <input type="password"
               class="form-control @error('password') is-invalid @enderror"
               id="password"
               name="password"
               {{ $isEdit ? '' : 'required' }}>
    </x-form-field>

    <x-form-field label="PO Number" for="po_number" name="po_number" help="Exactly 6 digits.">
        <input type="text"
               class="form-control @error('po_number') is-invalid @enderror"
               id="po_number"
               name="po_number"
               value="{{ old('po_number', $user->po_number ?? '') }}"
               maxlength="6"
               pattern="[0-9]{6}"
               autocomplete="off"
               placeholder="e.g. 423232">
    </x-form-field>

    <x-form-field label="Supervisor" for="supervisor_id" name="supervisor_id">
        <select class="form-select @error('supervisor_id') is-invalid @enderror"
                id="supervisor_id"
                name="supervisor_id">
            <option value="">No supervisor</option>
            @foreach($supervisors as $supervisor)
                <option value="{{ $supervisor->id }}" {{ (string) old('supervisor_id', $user->supervisor_id) === (string) $supervisor->id ? 'selected' : '' }}>
                    {{ $supervisor->name }}
                </option>
            @endforeach
        </select>
    </x-form-field>

    <x-form-options>
        <x-form-switch
            name="active"
            label="Active"
            help="Inactive users cannot log in and are removed from teams, agreements, and assignment pickers. Activity history is kept."
            :checked="old('active', $user->exists ? $user->active : true)"
            class="mb-0"
        />
    </x-form-options>
</x-section-card>

<x-section-card title="Scope">
    <x-project-program-scope-picker
        scope-id="user-scope"
        :projects="$projects"
        :selected-project-ids="$selectedProjectIds"
        :selected-program-ids="$selectedProgramIds"
        :show-scope-mode-selector="true"
        :selected-scope-mode="old('program_scope_mode', $user->program_scope_mode?->value ?? 'specific')"
        :lock-all="$user->exists && $user->program_scope_mode?->value === 'all'"
    />
    @if(! $isEdit)
        <x-form-field label="Teams" name="team_ids" class="mt-3" help="Optional. Create requires at least one in-scope program or team.">
            <x-token-picker
                picker-id="user-create-teams"
                name="team_ids[]"
                :options="($teams ?? collect())->map(fn ($team) => ['value' => $team->id, 'label' => $team->name, 'search' => $team->name])"
                :selected-ids="old('team_ids', [])"
                placeholder="Search teams..."
                entity="team"
            />
        </x-form-field>
    @endif
</x-section-card>

<x-section-card title="Permissions">
    <x-form-field label="Role" name="access_profile" :required="true">
        <div class="d-grid gap-2" data-access-profile-cards>
            <x-form-radio-card
                name="access_profile"
                value="admin_viewer"
                label="Admin / Enhanced Viewer"
                description="View or edit records in an assigned project, program, or system-wide scope."
                :checked="$selectedProfile === 'admin_viewer'"
                :required="true"
            />
            <div class="ps-4" data-privilege-panel>
                @include('admin.users.partials.privilege-ledger', [
                    'user' => $user,
                    'actor' => $actor,
                    'coverage' => $coverage,
                    'systemIsAdmin' => old('privilege_system_admin', $systemIsAdmin),
                    'ledgerProjects' => $ledgerProjects,
                    'ledgerPrograms' => $ledgerPrograms,
                ])
            </div>
            <x-form-radio-card
                name="access_profile"
                value="member"
                label="User"
                description="View assigned projects, programs, teams, agreements, and related records. Log and edit activities you are on."
                :checked="$selectedProfile === 'member'"
            />
            <x-form-radio-card
                name="access_profile"
                value="input"
                label="Input User"
                description="Home, profile, and activity logging only."
                :checked="$selectedProfile === 'input'"
            />
        </div>
    </x-form-field>

    <div data-supervisor-switch>
        <hr class="my-3">
        <x-form-options>
            <x-form-switch
                name="is_supervisor"
                label="Supervisor"
                help="Appears in supervisor pickers and can open a Supervisees list of direct reports."
                :checked="old('is_supervisor', $user->is_supervisor)"
                class="mb-0"
            />
        </x-form-options>
    </div>
</x-section-card>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const cards = document.querySelectorAll('input[name="access_profile"]');
        const supervisor = document.querySelector('[data-supervisor-switch]');
        const panel = document.querySelector('[data-privilege-panel]');
        const supervisorInput = supervisor?.querySelector('input[type="checkbox"]');

        function sync() {
            const selected = document.querySelector('input[name="access_profile"]:checked')?.value;
            if (panel) panel.classList.toggle('d-none', selected !== 'admin_viewer');
            if (supervisor) supervisor.classList.toggle('d-none', selected === 'input');
            if (selected === 'input' && supervisorInput) supervisorInput.checked = false;
        }

        cards.forEach((input) => input.addEventListener('change', sync));
        sync();
    });
</script>

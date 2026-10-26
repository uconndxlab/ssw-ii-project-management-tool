@php
    $actorAccess = $actor->access();
    $actorIsSystemAdmin = $actorAccess->isSystemAdmin();
    $coachSystemChecked = filter_var(old('duty_coach_system', $existingCoachSystem), FILTER_VALIDATE_BOOLEAN);
    $managerSystemChecked = filter_var(old('duty_manager_system', $existingManagerSystem), FILTER_VALIDATE_BOOLEAN);
    $coachProgramIds = old('duty_coach_program_ids', $existingCoachProgramIds);
    $managerProgramIds = old('duty_manager_program_ids', $existingManagerProgramIds);
@endphp

<div data-certification-duty-ledger>
    <p class="form-text mt-0">
        Coach and manager duties govern certification actions within their scope.
        They do not change access privileges or grant visibility.
    </p>

    <x-form-subsection title="Coach">
        <input type="hidden" name="duty_coach_present" value="1">
        @if($actorIsSystemAdmin)
            <x-form-switch
                name="duty_coach_system"
                label="System-wide"
                :checked="$coachSystemChecked"
                class="mb-2"
            />
        @endif
        <x-form-field label="Programs" class="mb-0">
            <x-token-picker
                picker-id="duty-coach-programs"
                name="duty_coach_program_ids[]"
                :items="$dutyProgramOptions"
                :selected-ids="$coachProgramIds"
                placeholder="Search programs..."
                :open-on-focus="false"
                :show-selected="true"
                height="180px"
                entity="program"
            />
        </x-form-field>
    </x-form-subsection>

    <x-form-subsection title="Manager">
        <input type="hidden" name="duty_manager_present" value="1">
        @if($actorIsSystemAdmin)
            <x-form-switch
                name="duty_manager_system"
                label="System-wide"
                :checked="$managerSystemChecked"
                class="mb-2"
            />
        @endif
        <x-form-field label="Programs" class="mb-0">
            <x-token-picker
                picker-id="duty-manager-programs"
                name="duty_manager_program_ids[]"
                :items="$dutyProgramOptions"
                :selected-ids="$managerProgramIds"
                placeholder="Search programs..."
                :open-on-focus="false"
                :show-selected="true"
                height="180px"
                entity="program"
            />
        </x-form-field>
    </x-form-subsection>
</div>

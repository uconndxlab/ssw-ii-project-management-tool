<?php

use App\Enums\ProgramScopeMode;
use App\Models\Agreement;
use App\Models\State;
use App\Models\User;

test('agreement store accepts a string program scope mode', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $state = State::factory()->create();
    $member = User::factory()->create();
    $member->programs()->attach($program);

    $this->actingAs($admin)
        ->post(route('agreements.store'), [
            'name' => 'Baseline Agreement',
            'program_scope_mode' => ProgramScopeMode::Specific->value,
            'project_ids' => [$project->id],
            'program_ids' => [$program->id],
            'state_ids' => [$state->id],
            'user_ids' => [$member->id],
            'principal_investigator_ids' => [$member->id],
            'time_tracking_mode' => 'none',
            'require_payor' => 0,
            'require_payee' => 0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $agreement = Agreement::query()->where('name', 'Baseline Agreement')->first();

    expect($agreement)->not->toBeNull()
        ->and($agreement->program_scope_mode)->toBe(ProgramScopeMode::Specific);
});

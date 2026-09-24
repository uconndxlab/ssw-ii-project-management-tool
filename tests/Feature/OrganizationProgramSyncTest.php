<?php

use App\Enums\ProgramScopeMode;
use App\Models\Organization;
use App\Models\State;

test('saving an organization syncs its programs', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $state = State::factory()->create();

    $this->actingAs($admin)
        ->post(route('organizations.store'), [
            'name' => 'Scoped Organization',
            'active' => 1,
            'state_ids' => [$state->id],
            'program_scope_mode' => ProgramScopeMode::Specific->value,
            'project_ids' => [$project->id],
            'program_ids' => [$program->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $organization = Organization::query()->where('name', 'Scoped Organization')->first();

    expect($organization)->not->toBeNull()
        ->and($organization->program_scope_mode)->toBe(ProgramScopeMode::Specific)
        ->and($organization->programs()->pluck('programs.id')->all())->toContain($program->id);
});

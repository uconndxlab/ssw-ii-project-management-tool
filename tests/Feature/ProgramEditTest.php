<?php

use App\Models\Program;
use App\Models\Project;

test('system admin program edit lists assigned projects', function () {
    $project = Project::factory()->create(['name' => 'Baseline Project']);
    $program = Program::factory()->create();
    $program->projects()->attach($project);

    $this->actingAs(createSystemAdmin())
        ->get(route('programs.edit', $program))
        ->assertOk()
        ->assertSee('Baseline Project');
});

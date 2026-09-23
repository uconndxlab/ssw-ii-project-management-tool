<?php

use App\Models\Program;
use App\Models\Project;
use App\Support\ProjectProgramScope;
use Illuminate\Support\Collection;

test('scope picker view data keeps selected ids when given a project collection', function () {
    $project = Project::factory()->create();
    $program = Program::factory()->create();
    $project->programs()->attach($program);
    $project->load('programs');

    $data = ProjectProgramScope::scopePickerViewData(
        Collection::make([$project]),
        [$project->id],
        [$program->id],
        'scope-test',
    );

    expect($data['selectedProjectIds'])->toBe([(string) $project->id])
        ->and($data['selectedProgramIds'])->toBe([(string) $program->id])
        ->and($data['scopeProjects'])->toHaveCount(1)
        ->and($data['programOptions'][0]['id'])->toBe($program->id);
});

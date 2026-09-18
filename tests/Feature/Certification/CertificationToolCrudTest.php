<?php

use App\Models\CertificationTool;
use App\Models\CertificationToolDimension;
use App\Models\CertificationToolScoreField;
use App\Models\User;

test('system admin can create a certification tool with nested dimensions and score fields', function () {
    $admin = createSystemAdmin();
    $payload = toolStorePayload();

    $response = $this->actingAs($admin)->post(route('certification-tools.store'), $payload);

    $tool = CertificationTool::where('name', 'COMET')->first();
    expect($tool)->not->toBeNull();
    expect($tool->slug)->toBe('comet');
    expect($tool->dimensions)->toHaveCount(1);
    expect($tool->dimensions->first()->options)->toHaveCount(2);
    expect($tool->scoreFields)->toHaveCount(2);
    expect($tool->programs)->toHaveCount(1);

    $response->assertRedirect(route('certification-tools.edit', $tool));
});

test('system admin can update a tool and sync nested rows including deletes', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->forProgram($program)->create(['name' => 'SAS']);
    $dimension = CertificationToolDimension::factory()->for($tool, 'tool')->create(['name' => 'Old Dim']);
    $field = CertificationToolScoreField::factory()->for($tool, 'tool')->create(['name' => 'Old Field']);

    $response = $this->actingAs($admin)->put(route('certification-tools.update', $tool), [
        'name' => 'SAS Updated',
        'active' => '1',
        'program_scope_mode' => 'specific',
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'dimensions' => [
            0 => [
                'id' => $dimension->id,
                '_delete' => '1',
            ],
            1 => [
                'name' => 'New Dim',
                'sort_order' => 0,
                'options' => [
                    0 => ['label' => 'Option A', 'sort_order' => 0],
                ],
            ],
        ],
        'score_fields' => [
            0 => [
                'id' => $field->id,
                'name' => 'Total',
                'unit' => 'number',
                'sort_order' => 0,
            ],
        ],
    ]);

    $tool->refresh();
    expect($tool->name)->toBe('SAS Updated');
    expect(CertificationToolDimension::find($dimension->id))->toBeNull();
    expect($tool->dimensions)->toHaveCount(1);
    expect($tool->dimensions->first()->name)->toBe('New Dim');
    expect($tool->scoreFields->first()->name)->toBe('Total');

    $response->assertRedirect(route('certification-tools.index'));
});

test('tool store skips blank nested rows', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)->post(route('certification-tools.store'), toolStorePayload([
        'dimensions' => [
            0 => ['name' => '', 'options' => []],
            1 => ['name' => 'Real', 'sort_order' => 0, 'options' => []],
        ],
        'score_fields' => [
            0 => ['name' => '', 'unit' => 'percent'],
        ],
    ]));

    $tool = CertificationTool::where('name', 'COMET')->first();
    expect($tool->dimensions)->toHaveCount(1);
    expect($tool->scoreFields)->toHaveCount(0);
});

test('system admin can view tool index create and edit pages', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->forProgram($program)->create();

    $this->actingAs($admin)->get(route('certification-tools.index'))->assertOk();
    $this->actingAs($admin)->get(route('certification-tools.create'))->assertOk();
    $this->actingAs($admin)->get(route('certification-tools.edit', $tool))->assertOk();
});

test('tool index returns table partial for htmx requests', function () {
    $admin = createSystemAdmin();

    $response = $this->actingAs($admin)
        ->withHeader('HX-Request', 'true')
        ->get(route('certification-tools.index'));

    $response->assertOk();
    $response->assertViewIs('admin.certification-tools.partials.table');
});

test('tool index supports search active and program filters', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    [$otherProject, $otherProgram] = createProjectWithProgram();

    $visible = CertificationTool::factory()->forProgram($program)->create(['name' => 'Alpha Tool', 'active' => true]);
    CertificationTool::factory()->forProgram($otherProgram)->create(['name' => 'Beta Tool', 'active' => false]);

    $this->actingAs($admin)
        ->get(route('certification-tools.index', ['search' => 'Alpha', 'active' => 1, 'program_id' => $program->id]))
        ->assertOk()
        ->assertSee('Alpha Tool')
        ->assertDontSee('Beta Tool');
});

test('tool index paginates at twenty per page', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();

    CertificationTool::factory()->count(21)->forProgram($program)->create();

    $response = $this->actingAs($admin)->get(route('certification-tools.index'));
    $response->assertOk();
    expect($response->viewData('certificationTools')->perPage())->toBe(20);
});

test('tool store requires name and unique name', function () {
    $admin = createSystemAdmin();
    CertificationTool::factory()->create(['name' => 'Taken']);

    $this->actingAs($admin)
        ->from(route('certification-tools.create'))
        ->post(route('certification-tools.store'), toolStorePayload(['name' => '']))
        ->assertSessionHasErrors('name');

    $this->actingAs($admin)
        ->from(route('certification-tools.create'))
        ->post(route('certification-tools.store'), toolStorePayload(['name' => 'Taken']))
        ->assertSessionHasErrors('name');
});

test('tool store requires program when scope mode is specific', function () {
    $admin = createSystemAdmin();
    [$project] = createProjectWithProgram();

    $this->actingAs($admin)
        ->from(route('certification-tools.create'))
        ->post(route('certification-tools.store'), [
            'name' => 'No Programs',
            'active' => '1',
            'program_scope_mode' => 'specific',
            'project_ids' => [$project->id],
            'program_ids' => [],
        ])
        ->assertSessionHasErrors('program_ids');
});

test('tool store rejects program not belonging to selected project', function () {
    $admin = createSystemAdmin();
    [$projectA, $programA] = createProjectWithProgram();
    [$projectB, $programB] = createProjectWithProgram();

    $this->actingAs($admin)
        ->from(route('certification-tools.create'))
        ->post(route('certification-tools.store'), [
            'name' => 'Bad Scope',
            'active' => '1',
            'program_scope_mode' => 'specific',
            'project_ids' => [$projectA->id],
            'program_ids' => [$programB->id],
        ])
        ->assertSessionHasErrors('program_ids');
});

test('tool store rejects invalid score field unit', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)
        ->from(route('certification-tools.create'))
        ->post(route('certification-tools.store'), toolStorePayload([
            'score_fields' => [
                0 => ['name' => 'Bad Unit', 'unit' => 'invalid'],
            ],
        ]))
        ->assertSessionHasErrors('score_fields.0.unit');
});

test('duplicate tool dimension slugs return validation errors not a server error', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)
        ->from(route('certification-tools.create'))
        ->post(route('certification-tools.store'), toolStorePayload([
            'name' => 'Duplicate Dimension Tool',
            'dimensions' => [
                0 => ['name' => 'Phase', 'sort_order' => 0, 'options' => []],
                1 => ['name' => 'Phase', 'sort_order' => 1, 'options' => []],
            ],
            'score_fields' => [],
        ]))
        ->assertSessionHasErrors('dimensions.1.name')
        ->assertRedirect();

    expect(CertificationTool::where('name', 'Duplicate Dimension Tool')->exists())->toBeFalse();
});

test('member cannot access certification tool routes', function () {
    $user = User::factory()->create();
    [$project, $program] = createProjectWithProgram();
    $tool = CertificationTool::factory()->forProgram($program)->create();

    $this->actingAs($user)->get(route('certification-tools.index'))->assertForbidden();
    $this->actingAs($user)->get(route('certification-tools.create'))->assertForbidden();
    $this->actingAs($user)->post(route('certification-tools.store'), toolStorePayload())->assertForbidden();
    $this->actingAs($user)->get(route('certification-tools.edit', $tool))->assertForbidden();
    $this->actingAs($user)->put(route('certification-tools.update', $tool), toolStorePayload())->assertForbidden();
    $this->actingAs($user)->delete(route('certification-tools.destroy', $tool))->assertForbidden();
});

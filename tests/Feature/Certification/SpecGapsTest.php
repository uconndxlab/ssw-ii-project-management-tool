<?php

use App\Models\Certificate;

/**
 * Doc 1 Phase F: "sort_order assigned from submitted order."
 * Certificate nested repeaters never persist sort_order from array position.
 */
test('certificate nested rows assign sort_order from submitted order', function () {
    $admin = createSystemAdmin();

    $this->actingAs($admin)->post(route('certificates.store'), certificateStorePayload([
        'roles' => [
            0 => ['name' => 'First Role', 'active' => '1'],
            1 => ['name' => 'Second Role', 'active' => '1'],
        ],
        'requirement_groups' => [
            0 => [
                'phase' => 'initial',
                'label' => 'First Group',
                'satisfy_mode' => 'all',
            ],
            1 => [
                'phase' => 'initial',
                'label' => 'Second Group',
                'satisfy_mode' => 'all',
            ],
        ],
        'requirements' => [
            0 => [
                'phase' => 'initial',
                'kind' => 'attestation',
                'label' => 'First Req',
                'target_count' => 1,
            ],
            1 => [
                'phase' => 'initial',
                'kind' => 'attestation',
                'label' => 'Second Req',
                'target_count' => 1,
            ],
        ],
    ]));

    $certificate = Certificate::where('name', 'NWIC Local Coach')->firstOrFail();

    expect($certificate->roles->pluck('sort_order')->all())->toBe([0, 1]);
    expect($certificate->requirementGroups->pluck('sort_order')->all())->toBe([0, 1]);
    expect($certificate->requirements->pluck('sort_order')->all())->toBe([0, 1]);
});

/**
 * Doc 1 Phase B item 3: "add a partial guard in validation rather than relying on the unique for NULLs."
 */
test('duplicate certificate role slugs return validation errors not a server error', function () {
    $admin = createSystemAdmin();

    $response = $this->actingAs($admin)->from(route('certificates.create'))->post(
        route('certificates.store'),
        certificateStorePayload([
            'roles' => [
                0 => ['name' => 'Observer', 'active' => '1'],
                1 => ['name' => 'Observer', 'active' => '1'],
            ],
            'requirements' => [],
            'requirement_groups' => [],
        ]),
    );

    $response->assertSessionHasErrors('roles');
    $response->assertStatus(302);
});

/**
 * Inferred from Phase B unique constraints on tool nested rows (same pattern as certification roles).
 */
test('duplicate tool dimension slugs return validation errors not a server error', function () {
    $admin = createSystemAdmin();

    $response = $this->actingAs($admin)->from(route('certification-tools.create'))->post(
        route('certification-tools.store'),
        toolStorePayload([
            'dimensions' => [
                0 => ['name' => 'Phase', 'sort_order' => 0, 'options' => []],
                1 => ['name' => 'Phase', 'sort_order' => 1, 'options' => []],
            ],
        ]),
    );

    $response->assertSessionHasErrors('dimensions');
    $response->assertStatus(302);
});

/**
 * Spine "Existing patterns to mirror": ScopeSync::validateSubmittedProgramsAreInAdminScope().
 * Program-scoped admin should not silently lose access to a record they just created.
 */
test('program-scoped admin cannot create certificate scoped to programs they do not administer', function () {
    [$projectA, $programA] = createProjectWithProgram();
    [$projectB, $programB] = createProjectWithProgram();
    $adminA = createProgramAdmin($programA);

    $response = $this->actingAs($adminA)->from(route('certificates.create'))->post(
        route('certificates.store'),
        [
            'name' => 'Out of Scope Cert',
            'active' => '1',
            'program_scope_mode' => 'specific',
            'project_ids' => [$projectB->id],
            'program_ids' => [$programB->id],
            'prerequisite_mode' => 'all',
        ],
    );

    $response->assertSessionHasErrors('program_ids');
    expect(Certificate::where('name', 'Out of Scope Cert')->exists())->toBeFalse();
});

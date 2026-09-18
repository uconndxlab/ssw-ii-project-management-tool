<?php

use App\Enums\CertificateDimensionRuleMode;
use App\Enums\CertificateRequirementKind;
use App\Enums\CertificateRequirementPhase;
use App\Models\ActivityType;
use App\Models\Certificate;
use App\Models\CertificationTool;
use App\Models\ContactFamily;

function storeToolAsAdmin(array $payload): CertificationTool
{
    $admin = createSystemAdmin();
    test()->actingAs($admin)->post(route('certification-tools.store'), $payload)->assertRedirect();

    return CertificationTool::where('name', $payload['name'])->firstOrFail();
}

test('acceptance tools can be authored per doc 1 acceptance 2', function () {
    [$project, $program] = createProjectWithProgram();
    $scope = [
        'active' => '1',
        'program_scope_mode' => 'specific',
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
    ];

    $comet = storeToolAsAdmin(array_merge($scope, [
        'name' => 'COMET',
        'dimensions' => [
            0 => [
                'name' => 'Phase',
                'sort_order' => 0,
                'options' => [
                    0 => ['label' => 'Phase 1', 'sort_order' => 0],
                    1 => ['label' => 'Phase 2', 'sort_order' => 1],
                    2 => ['label' => 'Phase 3', 'sort_order' => 2],
                    3 => ['label' => 'Phase 4', 'sort_order' => 3],
                ],
            ],
            1 => [
                'name' => 'Review Mode',
                'sort_order' => 1,
                'options' => [
                    0 => ['label' => 'Document', 'sort_order' => 0],
                    1 => ['label' => 'Full', 'sort_order' => 1],
                ],
            ],
        ],
        'score_fields' => [
            0 => ['name' => 'Overall Match %', 'unit' => 'percent', 'sort_order' => 0],
            1 => ['name' => 'Element Match %', 'unit' => 'percent', 'sort_order' => 1],
        ],
    ]));

    $supervisor = storeToolAsAdmin(array_merge($scope, [
        'name' => 'Supervisor Checklist',
        'dimensions' => [
            0 => [
                'name' => 'Phase',
                'sort_order' => 0,
                'options' => [
                    0 => ['label' => 'Phase 2', 'sort_order' => 0],
                    1 => ['label' => 'Phase 3', 'sort_order' => 1],
                    2 => ['label' => 'Phase 4', 'sort_order' => 2],
                ],
            ],
            1 => [
                'name' => 'Review Mode',
                'sort_order' => 1,
                'options' => [
                    0 => ['label' => 'Document', 'sort_order' => 0],
                    1 => ['label' => 'Full', 'sort_order' => 1],
                ],
            ],
        ],
        'score_fields' => [
            0 => ['name' => 'Overall Match %', 'unit' => 'percent', 'sort_order' => 0],
            1 => ['name' => 'Element 80%', 'unit' => 'percent', 'sort_order' => 1],
        ],
    ]));

    $sas = storeToolAsAdmin(array_merge($scope, [
        'name' => 'SAS',
        'dimensions' => [],
        'score_fields' => [
            0 => ['name' => 'Total', 'unit' => 'number', 'sort_order' => 0],
            1 => ['name' => 'Coaching', 'unit' => 'percent', 'sort_order' => 1],
            2 => ['name' => 'Communication', 'unit' => 'percent', 'sort_order' => 2],
            3 => ['name' => 'Analysis', 'unit' => 'percent', 'sort_order' => 3],
        ],
    ]));

    $crest = storeToolAsAdmin(array_merge($scope, [
        'name' => 'CREST',
        'dimensions' => [],
        'score_fields' => [],
    ]));

    expect($comet->dimensions)->toHaveCount(2);
    expect($comet->scoreFields)->toHaveCount(2);
    expect($supervisor->dimensions->first()->options)->toHaveCount(3);
    expect($sas->scoreFields)->toHaveCount(4);
    expect($crest->dimensions)->toHaveCount(0);
    expect($crest->scoreFields)->toHaveCount(0);
});

test('nwic local coach certificate is fully expressible including renewal', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $scope = [
        'active' => '1',
        'program_scope_mode' => 'specific',
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
        'prerequisite_mode' => 'all',
    ];

    $comet = storeToolAsAdmin(array_merge($scope, [
        'name' => 'COMET NWIC',
        'dimensions' => [
            0 => [
                'name' => 'Phase',
                'sort_order' => 0,
                'options' => collect(range(1, 4))->map(fn ($n, $i) => [
                    'label' => "Phase {$n}",
                    'sort_order' => $i,
                ])->all(),
            ],
            1 => [
                'name' => 'Review Mode',
                'sort_order' => 1,
                'options' => [
                    0 => ['label' => 'Document', 'sort_order' => 0],
                    1 => ['label' => 'Full', 'sort_order' => 1],
                ],
            ],
        ],
        'score_fields' => [
            0 => ['name' => 'Overall Match %', 'unit' => 'percent', 'sort_order' => 0],
            1 => ['name' => 'Element Match %', 'unit' => 'percent', 'sort_order' => 1],
        ],
    ]));

    $supervisor = storeToolAsAdmin(array_merge($scope, [
        'name' => 'Supervisor Checklist NWIC',
        'dimensions' => [
            0 => [
                'name' => 'Phase',
                'sort_order' => 0,
                'options' => [
                    0 => ['label' => 'Phase 2', 'sort_order' => 0],
                    1 => ['label' => 'Phase 3', 'sort_order' => 1],
                    2 => ['label' => 'Phase 4', 'sort_order' => 2],
                ],
            ],
            1 => [
                'name' => 'Review Mode',
                'sort_order' => 1,
                'options' => [
                    0 => ['label' => 'Document', 'sort_order' => 0],
                    1 => ['label' => 'Full', 'sort_order' => 1],
                ],
            ],
        ],
        'score_fields' => [
            0 => ['name' => 'Overall Match %', 'unit' => 'percent', 'sort_order' => 0],
            1 => ['name' => 'Element 80%', 'unit' => 'percent', 'sort_order' => 1],
        ],
    ]));

    $sas = storeToolAsAdmin(array_merge($scope, [
        'name' => 'SAS NWIC',
        'score_fields' => [
            0 => ['name' => 'Total', 'unit' => 'number', 'sort_order' => 0],
            1 => ['name' => 'Coaching', 'unit' => 'percent', 'sort_order' => 1],
            2 => ['name' => 'Communication', 'unit' => 'percent', 'sort_order' => 2],
            3 => ['name' => 'Analysis', 'unit' => 'percent', 'sort_order' => 3],
        ],
    ]));

    $crest = storeToolAsAdmin(array_merge($scope, ['name' => 'CREST NWIC']));

    $cometPhase = $comet->dimensions->firstWhere('name', 'Phase');
    $cometReview = $comet->dimensions->firstWhere('name', 'Review Mode');
    $fullReview = $cometReview->options->firstWhere('label', 'Full');
    $supervisorPhase = $supervisor->dimensions->firstWhere('name', 'Phase');
    $supervisorReview = $supervisor->dimensions->firstWhere('name', 'Review Mode');
    $supervisorFull = $supervisorReview->options->firstWhere('label', 'Full');
    $phase234 = $supervisorPhase->options->pluck('id')->all();

    $family = ContactFamily::factory()->create();
    $wraparoundTypes = collect(['Wraparound 101', 'Wraparound 102', 'Wraparound 201', 'Wraparound 401', 'Wraparound 402', 'Wraparound 501'])
        ->map(fn ($name) => ActivityType::factory()->create([
            'name' => $name,
            'contact_family_id' => $family->id,
        ]));

    $requirements = [
        0 => [
            'phase' => CertificateRequirementPhase::Initial->value,
            'kind' => CertificateRequirementKind::Attestation->value,
            'label' => 'Registration in InnovatePractice(c)',
            'target_count' => 1,
        ],
    ];

    foreach ($wraparoundTypes->values() as $index => $type) {
        $requirements[] = [
            'phase' => CertificateRequirementPhase::Initial->value,
            'kind' => CertificateRequirementKind::ActivityCount->value,
            'label' => $type->name,
            'contact_family_id' => $family->id,
            'activity_type_id' => $type->id,
            'target_count' => 1,
        ];
    }

    $requirements[] = [
        'phase' => CertificateRequirementPhase::Initial->value,
        'kind' => CertificateRequirementKind::ToolSubmission->value,
        'label' => 'CREST — 12 submissions',
        'certification_tool_id' => $crest->id,
        'target_count' => 12,
        'requires_passing' => '1',
    ];

    $requirements[] = [
        'phase' => CertificateRequirementPhase::Initial->value,
        'kind' => CertificateRequirementKind::ToolSubmission->value,
        'label' => 'Supervisor Checklist — 6',
        'certification_tool_id' => $supervisor->id,
        'target_count' => 6,
        'requires_passing' => '1',
        'threshold_note' => '85%/80%',
        'dimension_rules' => [
            0 => [
                'certification_tool_dimension_id' => $supervisorPhase->id,
                'mode' => CertificateDimensionRuleMode::Coverage->value,
                'option_ids' => $phase234,
            ],
            1 => [
                'certification_tool_dimension_id' => $supervisorReview->id,
                'mode' => CertificateDimensionRuleMode::Quota->value,
                'option_ids' => [$supervisorFull->id],
                'min_count' => 4,
            ],
        ],
    ];

    $requirements[] = [
        'phase' => CertificateRequirementPhase::Initial->value,
        'kind' => CertificateRequirementKind::Attestation->value,
        'label' => 'STEPS ability attestation',
        'target_count' => 1,
    ];

    $requirements[] = [
        'phase' => CertificateRequirementPhase::Initial->value,
        'kind' => CertificateRequirementKind::ToolSubmission->value,
        'label' => 'COMET — 6 across all 4 phases',
        'certification_tool_id' => $comet->id,
        'target_count' => 6,
        'requires_passing' => '1',
        'dimension_rules' => [
            0 => [
                'certification_tool_dimension_id' => $cometPhase->id,
                'mode' => CertificateDimensionRuleMode::Coverage->value,
                'option_ids' => $cometPhase->options->pluck('id')->all(),
            ],
            1 => [
                'certification_tool_dimension_id' => $cometReview->id,
                'mode' => CertificateDimensionRuleMode::Quota->value,
                'option_ids' => [$fullReview->id],
                'min_count' => 4,
            ],
        ],
    ];

    $requirements[] = [
        'phase' => CertificateRequirementPhase::Initial->value,
        'kind' => CertificateRequirementKind::ToolSubmission->value,
        'label' => 'SAS — 9/12 across 3 sessions',
        'certification_tool_id' => $sas->id,
        'target_count' => 3,
        'requires_passing' => '1',
        'threshold_note' => '75% per section',
    ];

    $requirements[] = [
        'phase' => CertificateRequirementPhase::Initial->value,
        'kind' => CertificateRequirementKind::Attestation->value,
        'label' => 'Participate in all NWIC coaching sessions',
        'target_count' => 1,
    ];

    $requirements[] = [
        'phase' => CertificateRequirementPhase::Initial->value,
        'kind' => CertificateRequirementKind::ActivityCount->value,
        'label' => 'WVCC',
        'target_count' => 3,
        'window_months' => 12,
    ];

    $requirements[] = [
        'phase' => CertificateRequirementPhase::Renewal->value,
        'kind' => CertificateRequirementKind::ToolSubmission->value,
        'label' => 'CREST renewal — 6 submissions',
        'certification_tool_id' => $crest->id,
        'target_count' => 6,
        'requires_passing' => '1',
    ];

    $this->actingAs($admin)->post(route('certificates.store'), array_merge($scope, [
        'name' => 'NWIC Local Coach',
        'default_window_months' => 12,
        'requirements' => $requirements,
    ]))->assertRedirect();

    $certificate = Certificate::where('name', 'NWIC Local Coach')->first();
    expect($certificate)->not->toBeNull();
    expect($certificate->requirements()->where('phase', CertificateRequirementPhase::Initial)->count())->toBe(14);
    expect($certificate->requirements()->where('phase', CertificateRequirementPhase::Renewal)->count())->toBe(1);

    $wvcc = $certificate->requirements->firstWhere('label', 'WVCC');
    expect($wvcc->window_months)->toBe(12);

    $supervisorReq = $certificate->requirements->firstWhere('label', 'Supervisor Checklist — 6');
    expect($supervisorReq->dimensionRules)->toHaveCount(2);
    expect($supervisorReq->threshold_note)->toBe('85%/80%');
});

test('trainer certificate can require coach or supervisor with role-filtered activity rows', function () {
    $admin = createSystemAdmin();
    [$project, $program] = createProjectWithProgram();
    $scope = [
        'active' => '1',
        'program_scope_mode' => 'specific',
        'project_ids' => [$project->id],
        'program_ids' => [$program->id],
    ];

    $coachCert = Certificate::factory()->forProgram($program)->create(['name' => 'Coach']);
    $supervisorCert = Certificate::factory()->forProgram($program)->create(['name' => 'Supervisor']);

    $w101 = ActivityType::factory()->create(['name' => 'W101']);
    $w102 = ActivityType::factory()->create(['name' => 'W102']);
    $roles = ['Attend', 'Observe', 'Co-Train', 'Be Observed'];
    $rolePayload = collect($roles)->mapWithKeys(fn ($name, $i) => [
        $i => ['name' => $name, 'active' => '1'],
    ])->all();

    $requirements = [];
    $reqIndex = 0;
    foreach ($roles as $roleName) {
        foreach ([$w101, $w102] as $type) {
            $requirements[$reqIndex++] = [
                'phase' => CertificateRequirementPhase::Initial->value,
                'kind' => CertificateRequirementKind::ActivityCount->value,
                'label' => "{$roleName} — {$type->name}",
                'activity_type_id' => $type->id,
                'certification_role_id' => null,
                'target_count' => 1,
            ];
        }
    }

    $this->actingAs($admin)->post(route('certificates.store'), array_merge($scope, [
        'name' => 'Trainer',
        'prerequisite_mode' => 'any',
        'prerequisite_certificate_ids' => [$coachCert->id, $supervisorCert->id],
        'roles' => $rolePayload,
        'requirements' => $requirements,
    ]))->assertRedirect();

    $trainer = Certificate::where('name', 'Trainer')->first();
    expect($trainer->prerequisite_mode)->toBe('any');
    expect($trainer->prerequisites->pluck('id')->sort()->values()->all())
        ->toBe(collect([$coachCert->id, $supervisorCert->id])->sort()->values()->all());
    expect($trainer->roles)->toHaveCount(4);
    expect($trainer->requirements)->toHaveCount(8);
});

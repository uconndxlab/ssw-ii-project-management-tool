<?php

use App\Models\Agreement;
use App\Models\AgreementDeliverable;
use App\Models\User;
use App\Support\AgreementDeliverableDisplay;

test('individual deliverable display reads assigned user targets from pivot', function () {
    $user = User::factory()->create();
    $agreement = Agreement::factory()->create();
    $agreement->users()->attach($user->id);

    $deliverable = AgreementDeliverable::factory()
        ->for($agreement)
        ->completionByUser('individual')
        ->create([
            'target_quantity' => 20,
        ]);

    $deliverable->users()->attach($user->id, [
        'target_quantity' => 10,
    ]);

    $agreement->load([
        'users',
        'teams.users',
        'deliverables.users',
        'deliverables.teams',
        'deliverables.contributions',
    ]);

    $progress = AgreementDeliverableDisplay::buildGroupedProgress($agreement)
        ->flatMap(fn (array $familyGroup) => $familyGroup['activity_groups'])
        ->flatMap(fn (array $activityGroup) => $activityGroup['program_groups'])
        ->flatMap(fn (array $programGroup) => $programGroup['deliverables'])
        ->first();

    expect($progress)->not->toBeNull()
        ->and($progress['individual_progress'])->toHaveCount(1)
        ->and((float) $progress['individual_progress']->first()['target'])->toBe(10.0)
        ->and((float) $progress['allocation_summary']['allocated'])->toBe(10.0);
});

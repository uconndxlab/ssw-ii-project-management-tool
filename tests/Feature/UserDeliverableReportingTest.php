<?php

use App\Models\Agreement;
use App\Models\Team;
use App\Models\User;
use App\Support\UserDeliverableReporting;

test('deliverable reports mark direct agreement membership', function () {
    $user = User::factory()->create();
    $agreement = Agreement::factory()->current()->create(['name' => 'Direct Agreement']);
    $agreement->users()->attach($user);
    $user->load(['agreements', 'teams.agreements', 'programs']);

    $report = UserDeliverableReporting::buildAgreementReports($user)->first();

    expect($report)->not->toBeNull()
        ->and($report['direct'])->toBeTrue()
        ->and($report['teams'])->toHaveCount(0);
});

test('deliverable reports include teams when access is only via a team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create(['name' => 'Field Team']);
    $agreement = Agreement::factory()->current()->create(['name' => 'Team Agreement']);
    $team->users()->attach($user);
    $team->agreements()->attach($agreement);
    $user->load(['agreements', 'teams.agreements', 'programs']);

    $report = UserDeliverableReporting::buildAgreementReports($user)->first();

    expect($report)->not->toBeNull()
        ->and($report['direct'])->toBeFalse()
        ->and($report['teams']->pluck('name')->all())->toContain('Field Team');
});

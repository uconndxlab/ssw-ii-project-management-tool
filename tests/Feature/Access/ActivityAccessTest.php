<?php

use App\Enums\AccessProfile;
use App\Enums\CertificationDuty;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Models\Activity;
use App\Models\ActivityCertificationCandidate;
use App\Models\ActivityReviewRequest;
use App\Models\Agreement;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\Program;
use App\Models\Team;
use App\Models\User;
use App\Models\UserCertificationDuty;

require_once __DIR__.'/AccessFixtures.php';

/** @param array{bool, bool, bool, bool} $expected */
function assertActivityAccess(User $actor, Activity $activity, array $expected, bool $systemView = false): void
{
    assertRecordAccess($actor, $activity, $expected[0]);
    $hidden = Activity::factory()->create();
    $states = [[$activity, $expected], [$hidden, [$systemView, $systemView, false, false]]];
    foreach (['none', 'visibility', 'reviewers', 'both'] as $priming) {
        $freshActor = User::findOrFail($actor->id);
        $access = $freshActor->access();
        $ids = [$activity->id, $hidden->id];
        if (in_array($priming, ['visibility', 'both'], true)) {
            $access->primeActivityVisibility([]);
            $access->primeActivityVisibility($ids);
        }
        if (in_array($priming, ['reviewers', 'both'], true)) {
            $access->primeCertificationReviewers([]);
            $access->primeCertificationReviewers($ids);
        }
        foreach ($states as [$record, $answers]) {
            expect($access->canViewActivity($record->refresh()))->toBe($answers[0]);
            expect($freshActor->can('view', $record->refresh()))->toBe($answers[0]);
            expect($record->refresh()->isLinkable($freshActor))->toBe($answers[0]);
            expect(Activity::visibleTo($freshActor)->whereKey($record->id)->exists())->toBe($answers[0]);
            expect($access->canViewActivityActionLog($record->refresh()))->toBe($answers[1]);
            expect($freshActor->can('viewActionLog', $record->refresh()))->toBe($answers[1]);
            expect($access->isCertificationReviewerOnActivity($record->refresh()))->toBe($answers[2]);
            expect($access->isCertificationCoachOnActivity($record->refresh()))->toBe($answers[3]);
        }
    }
}

test('loggers and Delivered By users see activities, including Input, but only logger references expose action logs', function (AccessProfile $profile, bool $participant, bool $viaReport) {
    $actor = User::factory()->create(['access_profile' => $profile, 'is_supervisor' => $viaReport]);
    $referenced = $viaReport ? User::factory()->create(['supervisor_id' => $actor->id]) : $actor;
    $activity = Activity::factory()->create($participant ? [] : ['user_id' => $referenced->id]);
    if ($participant) {
        $activity->participants()->attach($referenced);
    }
    $visible = ! $viaReport || $profile !== AccessProfile::Input;
    assertActivityAccess($actor, $activity, [$visible, $visible && ! $participant, false, false]);
    assertActivityAccess(User::factory()->create(['is_supervisor' => true]), $activity, [false, false, false, false]);
    if ($viaReport) {
        $grandparent = User::factory()->create(['is_supervisor' => true]);
        $actor->update(['supervisor_id' => $grandparent->id]);
        assertRecordAccess($grandparent, $activity, false);
        $actor->update(['is_supervisor' => false]);
        assertActivityAccess($actor, $activity, [false, false, false, false]);
    }
})->with(AccessProfile::cases())->with(['logger' => false, 'Delivered By' => true])->with(['self' => false, 'direct report' => true]);

test('supervising coaches and requested reviewers keep activity access without duties except Input, including cleared requests', function (AccessProfile $profile, string $reason, bool $viaReport) {
    $actor = User::factory()->create(['access_profile' => $profile, 'is_supervisor' => $viaReport]);
    $coach = $viaReport ? User::factory()->create(['supervisor_id' => $actor->id]) : $actor;
    $activity = Activity::factory()->create();
    if ($reason === 'enrollment') {
        $candidate = CertificationCandidate::factory()->create();
        CertificateEnrollment::factory()->create(['certification_candidate_id' => $candidate->id, 'supervising_coach_user_id' => $coach->id]);
        $link = ActivityCertificationCandidate::factory()->create(['activity_id' => $activity->id, 'certification_candidate_id' => $candidate->id]);
    } else {
        $link = ActivityReviewRequest::factory()->create(['activity_id' => $activity->id, 'coach_user_id' => $coach->id, 'cleared_at' => $reason === 'cleared request' ? now() : null]);
    }
    $visible = $profile !== AccessProfile::Input;
    assertActivityAccess($actor, $activity, [$visible, false, $visible, $visible]);
    assertActivityAccess(User::factory()->create(['is_supervisor' => true]), $activity, [false, false, false, false]);
    if ($viaReport) {
        $grandparent = User::factory()->create(['is_supervisor' => true]);
        $actor->update(['supervisor_id' => $grandparent->id]);
        assertActivityAccess($grandparent, $activity, [false, false, false, false]);
        $actor->update(['is_supervisor' => false]);
        assertActivityAccess($actor, $activity, [false, false, false, false]);
    }
    $link->delete();
    assertActivityAccess($actor, $activity, [false, false, false, false]);
})->with(AccessProfile::cases())->with(['enrollment', 'open request', 'cleared request'])->with(['self' => false, 'direct report' => true]);

test('activity or agreement program privileges grant view but agreement-only overlap does not expose action logs', function (PrivilegeCapability $capability, PrivilegeScopeType $scope, bool $viaAgreement) {
    [$project, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $actor = accessActor($capability, $scope, match ($scope) {
        PrivilegeScopeType::System => null,
        PrivilegeScopeType::Project => $project->id,
        PrivilegeScopeType::Program => $program->id,
    });
    $activity = Activity::factory()->create();
    if ($viaAgreement) {
        $agreement = Agreement::factory()->create();
        $agreement->programs()->attach($program);
        $activity->agreements()->attach($agreement);
        $activity->programs()->attach($otherProgram);
    } else {
        $activity->programs()->attach($program);
    }
    $system = $scope === PrivilegeScopeType::System;
    assertActivityAccess($actor, $activity, [true, $system || ! $viaAgreement, false, false], $system);
    if (! $system) {
        assertActivityAccess(createProgramViewer(Program::factory()->create()), $activity, [false, false, false, false]);
    }
})->with(PrivilegeCapability::cases())->with(PrivilegeScopeType::cases())->with(['activity programs' => false, 'agreement programs' => true]);

test('membership and duties alone do not expose other peoples activities', function (string $reason) {
    [, $program] = createProjectWithProgram();
    $actor = User::factory()->create();
    $activity = Activity::factory()->create();
    $activity->programs()->attach($program);
    $agreement = Agreement::factory()->create();
    $activity->agreements()->attach($agreement);
    if ($reason === 'agreement staff') {
        $agreement->users()->attach($actor);
    } elseif ($reason === 'program member') {
        $program->users()->attach($actor);
    } elseif ($reason === 'agreement team') {
        $team = Team::factory()->create();
        $team->users()->attach($actor);
        $agreement->teams()->attach($team);
    } else {
        UserCertificationDuty::factory()->atProgram($program)->create(['user_id' => $actor->id, 'duty' => $reason === 'coach' ? CertificationDuty::Coach : CertificationDuty::Manager]);
    }
    assertActivityAccess($actor, $activity, [false, false, false, $reason === 'coach']);
})->with(['agreement staff', 'program member', 'agreement team', 'coach', 'manager']);

test('an out-of-scope viewer cannot see action logs through program membership alone', function (bool $viaReport, bool $viaTeam) {
    $program = Program::factory()->create();
    $actor = createProgramViewer(Program::factory()->create());
    $actor->update(['is_supervisor' => $viaReport]);
    $member = $viaReport ? User::factory()->create(['supervisor_id' => $actor->id]) : $actor;
    if ($viaTeam) {
        $team = Team::factory()->create();
        $team->users()->attach($member);
        $program->teams()->attach($team);
    } else {
        $program->users()->attach($member);
    }
    $activity = Activity::factory()->create();
    $activity->programs()->attach($program);
    assertActivityAccess($actor, $activity, [false, false, false, false]);
})->with([false, true])->with([false, true]);

test('coach standing follows own program duties, never manager duties or a reports duties', function (CertificationDuty $duty, PrivilegeScopeType $scope, AccessProfile $profile) {
    [$project, $program] = createProjectWithProgram();
    $supervisor = User::factory()->create(['is_supervisor' => true]);
    $actor = User::factory()->create(['access_profile' => $profile, 'supervisor_id' => $supervisor->id]);
    $dutyRow = UserCertificationDuty::factory()->create([
        'user_id' => $actor->id, 'duty' => $duty, 'scope_type' => $scope,
        'scope_id' => match ($scope) {
            PrivilegeScopeType::System => null,
            PrivilegeScopeType::Project => $project->id,
            PrivilegeScopeType::Program => $program->id,
        },
    ]);
    $activity = Activity::factory()->create();
    $activity->programs()->attach($program);
    assertActivityAccess($actor, $activity, [false, false, false, $duty === CertificationDuty::Coach && $profile !== AccessProfile::Input]);
    assertActivityAccess($supervisor, $activity, [false, false, false, false]);
    $otherActivity = Activity::factory()->create();
    $otherActivity->programs()->attach(Program::factory()->create());
    expect(User::findOrFail($actor->id)->access()->isCertificationCoachOnActivity($otherActivity))->toBe($duty === CertificationDuty::Coach && $scope === PrivilegeScopeType::System && $profile !== AccessProfile::Input);
    $dutyRow->update(['revoked_at' => now()]);
    assertActivityAccess($actor, $activity, [false, false, false, false]);
})->with(CertificationDuty::cases())->with(PrivilegeScopeType::cases())->with(AccessProfile::cases());

test('a coach duty on an agreement program does not grant coach standing on an activity without that program', function () {
    $program = Program::factory()->create();
    $actor = User::factory()->create();
    UserCertificationDuty::factory()->coach()->atProgram($program)->create(['user_id' => $actor->id]);
    $agreement = Agreement::factory()->create();
    $agreement->programs()->attach($program);
    $activity = Activity::factory()->create();
    $activity->agreements()->attach($agreement);
    assertActivityAccess($actor, $activity, [false, false, false, false]);
});

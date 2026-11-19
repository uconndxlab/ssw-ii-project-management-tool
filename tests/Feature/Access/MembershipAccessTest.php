<?php

use App\Enums\AccessProfile;
use App\Enums\ProgramScopeMode;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\CertificationCandidate;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\State;
use App\Models\Team;
use App\Models\User;
use Tests\TestCase;

require_once __DIR__.'/AccessFixtures.php';

test('members and direct supervisors see assigned records but unrelated and Input users do not', function (string $class, bool $viaTeam, bool $viaReport) {
    $actor = User::factory()->create(['is_supervisor' => $viaReport]);
    $assignee = $viaReport ? User::factory()->create(['supervisor_id' => $actor->id]) : $actor;
    $record = $class::factory()->create();
    $assignment = $record;
    if ($class === Project::class) {
        $assignment = Program::factory()->create();
        $record->programs()->attach($assignment);
    }
    if ($class === State::class || $class === CertificationCandidate::class) {
        $assignment = Agreement::factory()->create();
        if ($class === State::class) {
            $assignment->states()->attach($record);
        } else {
            AgreementCertificationCandidate::factory()->create(['agreement_id' => $assignment->id, 'certification_candidate_id' => $record->id]);
        }
    }
    if ($viaTeam) {
        $team = Team::factory()->create();
        $team->users()->attach($assignee);
        $assignment->teams()->attach($team);
    } else {
        $assignment->users()->attach($assignee);
    }
    assertRecordAccess($actor, $record, true);
    assertRecordAccess(User::factory()->create(['is_supervisor' => true]), $record, false);

    // An indirect supervisor must not inherit membership, nor a user with the flag turned off.
    if ($viaReport) {
        $grandparent = User::factory()->create(['is_supervisor' => true]);
        $actor->update(['supervisor_id' => $grandparent->id]);
        assertRecordAccess($grandparent, $record, false);
        $actor->update(['is_supervisor' => false]);
        assertRecordAccess($actor, $record, false);
        $actor->update(['is_supervisor' => true]);
    }
    $actor->update(['access_profile' => AccessProfile::Input]);
    assertRecordAccess($actor, $record, false);
})->with([
    'project through program' => [Project::class, false],
    'project through program team' => [Project::class, true],
    'program member' => [Program::class, false],
    'program team member' => [Program::class, true],
    'team member' => [Team::class, false],
    'agreement staff' => [Agreement::class, false],
    'agreement team staff' => [Agreement::class, true],
    'organization staff' => [Organization::class, false],
    'state through agreement staff' => [State::class, false],
    'state through agreement team' => [State::class, true],
    'candidate through roster agreement staff' => [CertificationCandidate::class, false],
    'candidate through roster agreement team' => [CertificationCandidate::class, true],
])->with(['self' => false, 'direct report' => true]);

test('all-program membership expands program and project visibility but none and empty specific do not', function (string $class, ProgramScopeMode $mode, bool $viaTeam, bool $viaReport) {
    [$project, $program] = createProjectWithProgram();
    $record = $class === Project::class ? $project : $program;
    $actor = User::factory()->create(['is_supervisor' => $viaReport]);
    $member = $viaReport ? User::factory()->create(['supervisor_id' => $actor->id]) : $actor;
    if ($viaTeam) {
        $team = Team::factory()->create(['program_scope_mode' => $mode]);
        $team->users()->attach($member);
    } else {
        $member->update(['program_scope_mode' => $mode]);
    }
    assertRecordAccess($actor, $record, $mode === ProgramScopeMode::All);
    $actor->update(['access_profile' => AccessProfile::Input]);
    assertRecordAccess($actor, $record, false);
})->with([Project::class, Program::class])->with(ProgramScopeMode::cases())->with([false, true])->with([false, true]);

test('inactive agreement staff remain listed but cannot open the agreement', function (bool $viaReport) {
    $actor = User::factory()->create(['is_supervisor' => $viaReport]);
    $staff = $viaReport ? User::factory()->create(['supervisor_id' => $actor->id]) : $actor;
    $agreement = Agreement::factory()->inactive()->create();
    $agreement->users()->attach($staff);
    // Inactive membership keeps list visibility, not permission to open it.
    assertRecordAccess($actor, $agreement, false, true);
    /** @var TestCase $this */
    $this->actingAs(User::findOrFail($actor->id))->get(route('agreements.show', $agreement))->assertForbidden();
})->with([false, true]);

test('membership in the assigned program does not grant membership in other record types', function (string $class) {
    [, $program] = createProjectWithProgram();
    $record = accessRecordInProgram($class, $program);
    $actor = User::factory()->create();
    $program->users()->attach($actor);
    assertRecordAccess($actor, $record, false);
})->with([Team::class, Agreement::class, Organization::class, State::class, CertificationCandidate::class]);

test('supervisors inherit membership of inactive and Input reports', function (AccessProfile $reportProfile) {
    $supervisor = User::factory()->create(['is_supervisor' => true]);
    $report = User::factory()->create(['supervisor_id' => $supervisor->id, 'access_profile' => $reportProfile, 'active' => false]);
    $agreement = Agreement::factory()->create();
    $agreement->users()->attach($report);
    assertRecordAccess($supervisor, $agreement, true);
})->with(AccessProfile::cases());

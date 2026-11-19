<?php

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Models\Agreement;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Team;
use App\Models\User;

require_once __DIR__.'/AccessFixtures.php';

test('organization priming preserves visible and hidden answers for each source of access', function (string $reason) {
    [$project, $program] = createProjectWithProgram();
    $actor = match ($reason) {
        'system admin' => createSystemAdmin(),
        'system view' => accessActor(PrivilegeCapability::View, PrivilegeScopeType::System, null),
        'project view' => accessActor(PrivilegeCapability::View, PrivilegeScopeType::Project, $project->id),
        'program view' => createProgramViewer($program),
        'Input' => User::factory()->create(['access_profile' => AccessProfile::Input]),
        default => User::factory()->create(['is_supervisor' => $reason === 'report staff']),
    };
    $visible = Organization::factory()->create();
    $visible->programs()->attach($program);
    if ($reason === 'staff') {
        $visible->users()->attach($actor);
    }
    if ($reason === 'report staff') {
        $visible->users()->attach(User::factory()->create(['supervisor_id' => $actor->id]));
    }
    $hidden = Organization::factory()->create();
    $expectedVisible = $reason !== 'Input';
    $expectedHidden = in_array($reason, ['system admin', 'system view'], true);
    assertRecordAccess($actor, $visible, $expectedVisible);
    assertRecordAccess($actor, $hidden, $expectedHidden);
    $freshActor = User::findOrFail($actor->id);
    $access = $freshActor->access();
    $access->primeOrganizationVisibility([]);
    $access->primeOrganizationVisibility([$visible->id, $hidden->id]);
    foreach ([[$visible, $expectedVisible], [$hidden, $expectedHidden]] as [$record, $answer]) {
        expect($access->canViewRecord($record))->toBe($answer);
        expect($freshActor->can('view', $record))->toBe($answer);
        expect($record->isLinkable($freshActor))->toBe($answer);
        expect(Organization::visibleTo($freshActor)->whereKey($record->id)->exists())->toBe($answer);
    }
})->with(['system admin', 'system view', 'project view', 'program view', 'staff', 'report staff', 'Input']);

test('program membership and agreement staff do not imply organization membership', function () {
    $actor = User::factory()->create();
    $program = Program::factory()->create();
    $program->users()->attach($actor);
    $team = Team::factory()->create();
    $team->programs()->attach($program);
    $team->users()->attach($actor);
    $agreement = Agreement::factory()->create();
    $agreement->users()->attach($actor);
    $organization = Organization::factory()->create();
    $organization->programs()->attach($program);
    $agreement->organizations()->attach($organization);
    assertRecordAccess($actor, $organization, false);
});

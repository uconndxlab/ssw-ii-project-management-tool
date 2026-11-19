<?php

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\CertificateEnrollment;
use App\Models\CertificationCandidate;
use App\Models\State;
use App\Models\User;

require_once __DIR__.'/AccessFixtures.php';

test('enrollment supervising coaches and their direct supervisors see candidates without agreement access', function (bool $viaReport) {
    $actor = User::factory()->create(['is_supervisor' => $viaReport]);
    $coach = $viaReport ? User::factory()->create(['supervisor_id' => $actor->id]) : $actor;
    $candidate = CertificationCandidate::factory()->create();
    CertificateEnrollment::factory()->create(['certification_candidate_id' => $candidate->id, 'supervising_coach_user_id' => $coach->id]);
    assertRecordAccess($actor, $candidate, true);
    assertRecordAccess(User::factory()->create(['is_supervisor' => true]), $candidate, false);
    $grandparent = User::factory()->create(['is_supervisor' => true]);
    $actor->update(['supervisor_id' => $grandparent->id]);
    assertRecordAccess($grandparent, $candidate, ! $viaReport);
    $actor->update(['access_profile' => AccessProfile::Input]);
    assertRecordAccess($actor, $candidate, false);
})->with([false, true]);

test('system viewers need a roster link while system admins see orphan candidates', function () {
    $candidate = CertificationCandidate::factory()->create();
    assertRecordAccess(createSystemAdmin(), $candidate, true);
    assertRecordAccess(accessActor(PrivilegeCapability::View, PrivilegeScopeType::System, null), $candidate, false);
});

test('candidate and state visibility follow an inactive agreement list even when staff cannot open it', function () {
    $actor = User::factory()->create();
    $agreement = Agreement::factory()->inactive()->create();
    $agreement->users()->attach($actor);
    $candidate = CertificationCandidate::factory()->create();
    AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id]);
    $state = State::factory()->create();
    $agreement->states()->attach($state);
    // Inactive membership keeps list visibility, not permission to open it.
    assertRecordAccess($actor, $agreement, false, true);
    // The candidate/state queries use agreement visibility, without AgreementPolicy's active check.
    assertRecordAccess($actor, $candidate, true);
    assertRecordAccess($actor, $state, true);
});

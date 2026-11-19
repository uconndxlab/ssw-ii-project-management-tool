<?php

use App\Enums\AccessProfile;
use App\Enums\PrivilegeCapability;
use App\Enums\PrivilegeScopeType;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\AgreementCertificationCandidate;
use App\Models\Certificate;
use App\Models\CertificationCandidate;
use App\Models\CertificationInstrument;
use App\Models\ContactFamily;
use App\Models\LoggingField;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\State;
use App\Models\SubActivity;
use App\Models\Team;
use App\Models\User;
use App\Models\UserPrivilege;
use Illuminate\Database\Eloquent\Model;

/** @return array<string, array{class-string<Model>, string, bool}> */
function accessRecordTypes(): array
{
    return [
        'Project' => [Project::class, 'projects.show', false],
        'Program' => [Program::class, 'programs.show', false],
        'Team' => [Team::class, 'teams.show', false],
        'Agreement' => [Agreement::class, 'agreements.show', false],
        'Organization' => [Organization::class, 'organizations.show', false],
        'State' => [State::class, 'states.show', false],
        'Activity' => [Activity::class, 'activities.show', false],
        'User' => [User::class, 'users.show', false],
        'CertificationCandidate' => [CertificationCandidate::class, 'certification-candidates.show', false],
        // These four resources have no show route; their edit page is the record endpoint.
        'ContactFamily' => [ContactFamily::class, 'contact-families.edit', true],
        'LoggingField' => [LoggingField::class, 'logging-fields.show', true],
        'ActivityType' => [ActivityType::class, 'activity-types.edit', true],
        'SubActivity' => [SubActivity::class, 'sub-activities.edit', true],
        'CertificationInstrument' => [CertificationInstrument::class, 'certification-instruments.edit', false],
        'Certificate' => [Certificate::class, 'certificates.show', false],
    ];
}

/** @return Activity|ActivityType|Agreement|Certificate|CertificationCandidate|CertificationInstrument|ContactFamily|LoggingField|Organization|Program|Project|State|SubActivity|Team|User */
function accessRecordInProgram(string $class, Program $program): Model
{
    if ($class === Project::class) {
        return $program->projects()->firstOrFail();
    }
    if ($class === Program::class) {
        return $program;
    }
    if ($class === State::class || $class === CertificationCandidate::class) {
        $agreement = Agreement::factory()->create();
        $agreement->programs()->attach($program);
        if ($class === State::class) {
            $state = State::factory()->create();
            $agreement->states()->attach($state);

            return $state;
        }
        $candidate = CertificationCandidate::factory()->create();
        AgreementCertificationCandidate::factory()->create(['agreement_id' => $agreement->id, 'certification_candidate_id' => $candidate->id]);

        return $candidate;
    }
    $record = $class::factory()->create();
    $record->programs()->attach($program);

    return $record;
}

function accessActor(PrivilegeCapability $capability, PrivilegeScopeType $scope, ?int $scopeId, AccessProfile $profile = AccessProfile::AdminViewer): User
{
    $actor = User::factory()->create(['access_profile' => $profile]);
    UserPrivilege::factory()->create(['user_id' => $actor->id, 'capability' => $capability, 'scope_type' => $scope, 'scope_id' => $scopeId]);

    return $actor;
}

/** @param Activity|ActivityType|Agreement|Certificate|CertificationCandidate|CertificationInstrument|ContactFamily|LoggingField|Organization|Program|Project|State|SubActivity|Team|User $record */
function assertRecordAccess(User $actor, Model $record, bool $single, ?bool $listed = null): void
{
    // Each surface gets a separate User instance: WeakMap caches cannot mask a disagreement.
    expect(User::findOrFail($actor->id)->can('view', $record->refresh()))->toBe($single);
    expect($record->refresh()->isLinkable(User::findOrFail($actor->id)))->toBe($single);
    expect($record::visibleTo(User::findOrFail($actor->id))->whereKey($record->getKey())->exists())->toBe($listed ?? $single);
}

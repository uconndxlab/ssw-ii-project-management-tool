<?php

use App\Enums\AccessProfile;
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
use App\Models\UserPrivilege;
use App\Support\Authorization\AccessReasons;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/Access/AccessFixtures.php';

function assertMyRole(User $actor, Activity|Agreement $record, array $labels): void
{
    $actor = User::findOrFail($actor->id);
    $records = $record::query()->whereKey($record->id)->get();
    $reasons = AccessReasons::forPage($records, $actor)[$record->id];

    expect(array_column($reasons, 'label'))->toBe($labels);
    expect($record::visibleTo($actor)->whereKey($record->id)->exists())->toBe($labels !== []);
    foreach ($reasons as $reason) {
        expect($reason['explanation'])->not->toBeEmpty();
    }
}

test('activity personal references explain visibility with exactly the matching tag', function (string $relation, string $label, AccessProfile $profile) {
    $actor = User::factory()->create(['access_profile' => $profile]);
    $activity = Activity::factory()->create($relation === 'logger' ? ['user_id' => $actor->id] : []);
    if ($relation === 'participants') {
        $activity->participants()->attach($actor);
    }

    assertMyRole($actor, $activity, [$label]);
})->with(['logger' => ['logger', 'Logged'], 'delivery' => ['participants', 'Delivered by']])->with([AccessProfile::Member, AccessProfile::Input]);

test('review requests and supervising coach enrollments explain reviewer visibility', function (string $source) {
    $actor = User::factory()->create();
    $activity = Activity::factory()->create();
    if ($source === 'request') {
        ActivityReviewRequest::factory()->create(['activity_id' => $activity->id, 'coach_user_id' => $actor->id]);
    } else {
        $candidate = CertificationCandidate::factory()->create();
        ActivityCertificationCandidate::factory()->create(['activity_id' => $activity->id, 'certification_candidate_id' => $candidate->id]);
        CertificateEnrollment::factory()->create(['certification_candidate_id' => $candidate->id, 'supervising_coach_user_id' => $actor->id]);
    }

    assertMyRole($actor, $activity, ['Reviewer']);
    $actor->update(['access_profile' => AccessProfile::Input]);
    assertMyRole($actor, $activity, []);
})->with(['request', 'enrollment']);

test('each direct report reference produces supervisor standing without personal tags', function (string $source) {
    $actor = User::factory()->create(['is_supervisor' => true]);
    $report = User::factory()->create(['supervisor_id' => $actor->id]);
    $activity = Activity::factory()->create($source === 'logger' ? ['user_id' => $report->id] : []);
    if ($source === 'delivery') {
        $activity->participants()->attach($report);
    } elseif ($source === 'request') {
        ActivityReviewRequest::factory()->create(['activity_id' => $activity->id, 'coach_user_id' => $report->id]);
    } elseif ($source === 'enrollment') {
        $candidate = CertificationCandidate::factory()->create();
        ActivityCertificationCandidate::factory()->create(['activity_id' => $activity->id, 'certification_candidate_id' => $candidate->id]);
        CertificateEnrollment::factory()->create(['certification_candidate_id' => $candidate->id, 'supervising_coach_user_id' => $report->id]);
    }

    assertMyRole($actor, $activity, ['Supervisor']);
})->with(['logger', 'delivery', 'request', 'enrollment']);

test('agreement membership and supervision explain the same membership predicates', function (string $source, string $label) {
    $actor = User::factory()->create(['is_supervisor' => true]);
    $member = str_starts_with($source, 'report') ? User::factory()->create(['supervisor_id' => $actor->id]) : $actor;
    $agreement = Agreement::factory()->create();
    if (str_ends_with($source, 'team')) {
        $team = Team::factory()->create();
        $team->users()->attach($member);
        $agreement->teams()->attach($team);
    } else {
        $agreement->users()->attach($member);
    }

    assertMyRole($actor, $agreement, [$label]);
})->with(['staff' => ['staff', 'Staff'], 'team' => ['team', 'Team'], 'report staff' => ['report staff', 'Supervisor'], 'report team' => ['report team', 'Supervisor']]);

test('program and project privileges have one named tag for each matching scope', function (string $class, PrivilegeCapability $capability, PrivilegeScopeType $scope) {
    [$project, $program] = createProjectWithProgram();
    $scopeRecord = $scope === PrivilegeScopeType::Project ? $project : $program;
    $actor = accessActor($capability, $scope, $scopeRecord->id);
    if ($capability === PrivilegeCapability::Admin) {
        UserPrivilege::factory()->create(['user_id' => $actor->id, 'capability' => PrivilegeCapability::View, 'scope_type' => $scope, 'scope_id' => $scopeRecord->id]);
    }
    $record = accessRecordInProgram($class, $program);

    assertMyRole($actor, $record, [($capability === PrivilegeCapability::Admin ? 'Admin' : 'Viewer').' · '.$scopeRecord->name]);
})->with([Activity::class, Agreement::class])->with([PrivilegeCapability::Admin, PrivilegeCapability::View])->with([PrivilegeScopeType::Program, PrivilegeScopeType::Project]);

test('an activity inherits named privilege reasons from agreements and keeps each matching scope once', function () {
    [$project, $program] = createProjectWithProgram();
    $actor = createProgramAdmin($program);
    UserPrivilege::factory()->programViewer($program)->create(['user_id' => $actor->id]);
    UserPrivilege::factory()->create(['user_id' => $actor->id, 'capability' => PrivilegeCapability::View, 'scope_type' => PrivilegeScopeType::Project, 'scope_id' => $project->id]);
    $otherProgram = Program::factory()->create();
    UserPrivilege::factory()->programViewer($otherProgram)->create(['user_id' => $actor->id]);
    $agreement = accessRecordInProgram(Agreement::class, $program);
    $activity = Activity::factory()->create();
    $activity->agreements()->attach($agreement);
    $activity->programs()->attach($otherProgram);

    $viewLabels = ['Viewer · '.$project->name, 'Viewer · '.$otherProgram->name];
    natcasesort($viewLabels);
    assertMyRole($actor, $activity, ['Admin · '.$program->name, ...array_values($viewLabels)]);
});

test('system access is explained even for records with no assigned programs', function (string $class, PrivilegeCapability $capability, string $label) {
    $actor = accessActor($capability, PrivilegeScopeType::System, null);
    $record = $class::factory()->create();

    assertMyRole($actor, $record, [$label]);
})->with([Activity::class, Agreement::class])->with([[PrivilegeCapability::Admin, 'System admin'], [PrivilegeCapability::View, 'System viewer']]);

test('inactive agreement membership never explains access granted by privilege overlap', function () {
    $program = Program::factory()->create();
    $actor = createProgramViewer($program);
    $agreement = Agreement::factory()->create(['active' => false]);
    $agreement->users()->attach($actor);
    $agreement->programs()->attach($program);

    assertMyRole($actor, $agreement, ['Viewer · '.$program->name]);
});

test('all visible index rows have reasons and every reason is rendered in HTMX responses', function (string $class, string $route, string $variable) {
    $actor = User::factory()->create();
    if ($class === Activity::class) {
        $record = Activity::factory()->create(['user_id' => $actor->id]);
        $record->participants()->attach($actor);
    } else {
        $record = Agreement::factory()->create();
        $record->users()->attach($actor);
        $team = Team::factory()->create();
        $team->users()->attach($actor);
        $record->teams()->attach($team);
    }
    $class::factory()->create();

    foreach ([false, true] as $htmx) {
        $response = $this->actingAs($actor)->withHeader('HX-Request', $htmx ? 'true' : 'false')->get(route($route))->assertOk()->assertSee('My role');
        $rows = $response->viewData($variable);
        $reasons = $response->viewData('roleReasons');
        expect($rows->modelKeys())->toBe([$record->id]);
        foreach ($rows as $row) {
            expect($reasons[$row->id])->toHaveCount(2);
            $response->assertSee('title="'.e($reasons[$row->id][0]['explanation']).'"', false);
            foreach ($reasons[$row->id] as $reason) {
                $response->assertSee($reason['label'])->assertSee($reason['explanation']);
            }
        }
    }
})->with([[Activity::class, 'activities.index', 'activities'], [Agreement::class, 'agreements.index', 'agreements']]);

test('reason overflow follows the house menu pattern with one tag and a complete plain text list', function () {
    $reasons = [
        ['label' => 'Logged', 'explanation' => 'You logged this activity.'],
        ['label' => 'Delivered by', 'explanation' => 'You delivered this activity.'],
        ['label' => 'Admin · Wraparound', 'explanation' => 'You admin Wraparound.'],
        ['label' => 'System admin', 'explanation' => 'You admin the system.'],
    ];
    $html = Blade::render('<x-access-reason-list :reasons="$reasons" />', compact('reasons'));
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    expect($xpath->query('//div[contains(@class,"app-access-reasons")]/span[contains(@class,"app-tag")]'))->toHaveCount(1);
    expect($xpath->query('//ul/li'))->toHaveCount(4);
    expect($xpath->query('//a'))->toHaveCount(0);
    expect($html)->toContain('>+3</button>', 'Show all 4 reasons', 'data-bs-popper-config', 'System admin', 'You admin Wraparound.');
    expect(Blade::render('<x-access-reason-list />'))->toContain('<span class="text-muted">—</span>');
});

test('embedded activity tables retain agreements and my role for agreement and candidate contexts', function (string $context) {
    $actor = createSystemAdmin();
    $agreement = Agreement::factory()->create();
    $activity = Activity::factory()->create();
    $activity->agreements()->attach($agreement);
    $query = ['embedded' => 1, 'agreement_id' => $agreement->id];
    if ($context === 'candidate') {
        $candidate = CertificationCandidate::factory()->create();
        ActivityCertificationCandidate::factory()->create(['activity_id' => $activity->id, 'certification_candidate_id' => $candidate->id]);
        $query = ['embedded' => 1, 'certification_candidate_id' => $candidate->id];
    }

    $this->actingAs($actor)->withHeader('HX-Request', 'true')->get(route('activities.index', $query))
        ->assertOk()->assertSee('data-label="Agreement"', false)->assertSee('My role')->assertSee($agreement->name)->assertSee('System admin');
})->with(['agreement', 'candidate']);

test('index query count stays fixed from two to eight rows including my role tags', function (string $class, string $route, string $profile) {
    $program = Program::factory()->create();
    $actor = match ($profile) {
        'member' => User::factory()->create(),
        'scoped' => createProgramAdmin($program),
        'system' => createSystemAdmin(),
    };
    $counts = [];
    foreach ([2, 6] as $additional) {
        foreach ($class::factory()->count($additional)->create() as $record) {
            $record->programs()->attach($program);
            if ($record instanceof Activity) {
                $record->participants()->attach($actor);
            } else {
                $record->users()->attach($actor);
            }
        }
        app()->forgetScopedInstances();
        $this->actingAs(User::findOrFail($actor->id));
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->get(route($route))->assertOk();

        $counts[] = count(DB::getQueryLog());
        DB::disableQueryLog();
    }
    expect($counts[1])->toBe($counts[0]);
})->with([[Activity::class, 'activities.index'], [Agreement::class, 'agreements.index']])->with(['member', 'scoped', 'system']);

test('reason parity holds across visible and hidden rows for every access profile', function (string $class, string $route, string $variable) {
    [$project, $program] = createProjectWithProgram();
    $member = User::factory()->create(['is_supervisor' => true]);
    $report = User::factory()->create(['supervisor_id' => $member->id]);
    $scoped = createProgramAdmin($program);
    $projectViewer = accessActor(PrivilegeCapability::View, PrivilegeScopeType::Project, $project->id);
    $system = createSystemAdmin();
    $input = User::factory()->create(['access_profile' => AccessProfile::Input]);
    $records = $class::factory()->count(4)->create();
    $records[0]->programs()->attach($program);
    if ($class === Activity::class) {
        $records[1]->update(['user_id' => $member->id]);
        $records[2]->participants()->attach($report);
        $records[3]->participants()->attach($input);
    } else {
        $records[1]->users()->attach($member);
        $team = Team::factory()->create();
        $team->users()->attach($report);
        $records[2]->teams()->attach($team);
    }
    $actors = $class === Activity::class ? [$member, $scoped, $projectViewer, $system, $input] : [$member, $scoped, $projectViewer, $system];

    foreach ($actors as $actor) {
        $actor = User::findOrFail($actor->id);
        $visible = $class::visibleTo($actor)->pluck('id')->sort()->values()->all();
        $reasoned = collect(AccessReasons::forPage($records, $actor))->filter()->keys()->sort()->values()->all();
        expect($reasoned)->toBe($visible);
        $response = $this->actingAs($actor)->get(route($route))->assertOk();
        foreach ($response->viewData($variable) as $row) {
            expect($response->viewData('roleReasons')[$row->id])->not->toBeEmpty();
        }
    }
})->with([[Activity::class, 'activities.index', 'activities'], [Agreement::class, 'agreements.index', 'agreements']]);

test('role tags and explanations escape scope names in the tag and overflow menu', function () {
    $name = '<script>alert("role")</script>';
    $reasons = array_fill(0, 3, ['label' => 'Admin · '.$name, 'explanation' => 'You admin '.$name.'.']);

    $html = Blade::render('<x-access-reason-list :reasons="$reasons" />', compact('reasons'));

    expect($html)->toContain('&lt;script&gt;')->not->toContain('<script>');
});

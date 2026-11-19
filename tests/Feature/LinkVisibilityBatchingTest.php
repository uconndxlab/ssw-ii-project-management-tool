<?php

use App\Models\Activity;
use App\Models\ActivityReviewRequest;
use App\Models\Agreement;
use App\Models\Program;
use App\Models\User;
use App\Support\Authorization\HydratedRecordIds;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/Access/AccessFixtures.php';

test('agreement index visibility queries stay constant as the page grows from two to eight records', function () {
    $actor = User::factory()->create();
    $counts = [];

    foreach ([2, 6] as $additional) {
        $agreements = Agreement::factory()->count($additional)->create();
        foreach ($agreements as $agreement) {
            $agreement->users()->attach($actor);
        }

        app()->forgetScopedInstances();
        $this->actingAs(User::findOrFail($actor->id));
        DB::enableQueryLog();
        DB::flushQueryLog();

        $response = $this->get(route('agreements.index'))->assertOk();

        $counts[] = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'from "agreements" where')
            && (str_starts_with($query['query'], 'select exists(')
                || str_starts_with($query['query'], 'select "id"'))
        )->count();
        DB::disableQueryLog();

        foreach ($agreements as $agreement) {
            $response->assertSee('href="'.route('agreements.show', $agreement).'"', false);
        }
    }

    expect($counts)->toBe([1, 1]);
});

test('an activity page links only the visible agreement from an eager loaded batch', function () {
    $actor = User::factory()->create();
    $activity = Activity::factory()->create(['user_id' => $actor->id]);
    $visible = Agreement::factory()->create(['name' => 'Visible agreement']);
    $hidden = Agreement::factory()->create(['name' => 'Hidden agreement']);
    $visible->users()->attach($actor);
    $activity->agreements()->attach([$visible->id, $hidden->id]);

    $this->actingAs($actor)->get(route('activities.show', $activity))
        ->assertOk()
        ->assertSee('Visible agreement')->assertSee('Hidden agreement')
        ->assertSee('href="'.route('agreements.show', $visible).'"', false)
        ->assertDontSee('href="'.route('agreements.show', $hidden).'"', false);
});

test('automatic batches cache both visible and hidden answers independently for each viewer', function (string $class) {
    [, $program] = createProjectWithProgram();
    [, $otherProgram] = createProjectWithProgram();
    $visible = accessRecordInProgram($class, $program);
    $hidden = accessRecordInProgram($class, $otherProgram);
    $actor = createProgramAdmin($program);
    $otherActor = createProgramAdmin($otherProgram);
    $records = $class::whereKey([$visible->id, $hidden->id])->get()->keyBy('id');
    $visible = $records[$visible->id];
    $hidden = $records[$hidden->id];

    DB::enableQueryLog();
    DB::flushQueryLog();
    expect($actor->access()->canViewRecord($hidden))->toBeFalse();
    $count = count(DB::getQueryLog());
    expect($actor->access()->canViewRecord($visible))->toBeTrue();
    expect($actor->access()->canViewRecord($hidden))->toBeFalse();
    expect(DB::getQueryLog())->toHaveCount($count);
    DB::disableQueryLog();

    expect($otherActor->access()->canViewRecord($visible))->toBeFalse();
    expect($otherActor->access()->canViewRecord($hidden))->toBeTrue();
})->with(array_map(fn (array $type) => [$type[0]], accessRecordTypes()));

test('activity reviewer and action log predicates keep separate batch answers', function () {
    $program = Program::factory()->create();
    $actor = createProgramViewer($program);
    $reviewed = Activity::factory()->create();
    $inScope = Activity::factory()->create();
    $inScope->programs()->attach($program);
    ActivityReviewRequest::factory()->create(['activity_id' => $reviewed->id, 'coach_user_id' => $actor->id]);
    [$reviewed, $inScope] = Activity::whereKey([$reviewed->id, $inScope->id])->orderBy('id')->get()->all();

    expect($actor->access()->isCertificationReviewerOnActivity($reviewed))->toBeTrue();
    expect($actor->access()->isCertificationReviewerOnActivity($inScope))->toBeFalse();
    expect($actor->access()->canViewActivityActionLog($reviewed))->toBeFalse();
    expect($actor->access()->canViewActivityActionLog($inScope))->toBeTrue();
    expect($actor->access()->canViewActivity($reviewed))->toBeTrue();
    expect($actor->access()->canViewActivity($inScope))->toBeTrue();
});

test('supervisor exceptions do not turn cached user membership denials into grants', function () {
    $actor = createProgramAdmin(Program::factory()->create());
    $actor->update(['is_supervisor' => true]);
    $report = User::factory()->create(['supervisor_id' => $actor->id]);
    $member = User::factory()->create();
    $member->programs()->attach($actor->access()->adminProgramIds());
    [$report, $member] = User::whereKey([$report->id, $member->id])->orderBy('id')->get()->all();

    expect($actor->access()->canViewUser($member))->toBeTrue();
    expect($actor->access()->canViewUser($report))->toBeTrue();
    expect($actor->access()->userIsInViewMembership($report))->toBeFalse();
});

test('pending ids are chunked and records retrieved after a batch are checked later', function () {
    $actor = User::factory()->create();
    $visible = Agreement::factory()->create();
    $visible->users()->attach($actor);
    $collector = app(HydratedRecordIds::class);
    foreach (range(-1000, -1) as $id) {
        $collector->add(Agreement::class, $id);
    }
    $visible = $visible->fresh();

    DB::enableQueryLog();
    DB::flushQueryLog();
    expect($actor->access()->canViewRecord($visible))->toBeTrue();
    expect(DB::getQueryLog())->toHaveCount(2);

    $hidden = Agreement::factory()->create()->fresh();
    DB::flushQueryLog();
    expect($actor->access()->canViewRecord($hidden))->toBeFalse();
    expect(DB::getQueryLog())->toHaveCount(1);
    DB::disableQueryLog();

    app()->forgetScopedInstances();
    expect(app(HydratedRecordIds::class)->forType(Agreement::class))->toBe([]);
});

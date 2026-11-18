<?php

use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('activities index sorts by agreement name on postgres', function () {
    $admin = createSystemAdmin();
    $activityType = ActivityType::factory()->create();

    $alpha = Agreement::factory()->create(['name' => 'Alpha Agreement']);
    $mid = Agreement::factory()->create(['name' => 'Mid Agreement']);
    $zulu = Agreement::factory()->create(['name' => 'Zulu Agreement']);

    $alphaActivity = Activity::factory()->create([
        'user_id' => $admin->id,
        'activity_type_id' => $activityType->id,
        'engagement_date' => '2026-01-15',
    ]);
    $alphaActivity->agreements()->attach([$alpha->id, $mid->id]);

    $zuluActivity = Activity::factory()->create([
        'user_id' => $admin->id,
        'activity_type_id' => $activityType->id,
        'engagement_date' => '2026-02-20',
    ]);
    $zuluActivity->agreements()->attach($zulu);

    $unlinked = Activity::factory()->create([
        'user_id' => $admin->id,
        'activity_type_id' => $activityType->id,
        'engagement_date' => '2026-03-01',
    ]);

    $ascending = $this->actingAs($admin)->get(route('activities.index', [
        'activity_type_id' => $activityType->id,
        'sort' => 'agreement',
        'direction' => 'asc',
    ]));

    $ascending->assertOk();
    $ascending->assertSeeInOrder([
        'Mar 01, 2026',
        'Jan 15, 2026',
        'Feb 20, 2026',
    ]);
    expect(substr_count($ascending->getContent(), 'Activity actions for Jan 15, 2026'))->toBe(1)
        ->and(substr_count($ascending->getContent(), 'Activity actions for Feb 20, 2026'))->toBe(1)
        ->and(substr_count($ascending->getContent(), 'Activity actions for Mar 01, 2026'))->toBe(1);

    $descending = $this->actingAs($admin)->get(route('activities.index', [
        'activity_type_id' => $activityType->id,
        'sort' => 'agreement',
        'direction' => 'desc',
    ]));

    $descending->assertOk();
    $descending->assertSeeInOrder([
        'Feb 20, 2026',
        'Jan 15, 2026',
        'Mar 01, 2026',
    ]);
});

test('activities sort by the first related name with deterministic ties', function (string $sort) {
    $admin = createSystemAdmin();
    $type = ActivityType::factory()->create();
    $activities = Activity::factory()->count(4)->create(['user_id' => $admin->id, 'activity_type_id' => $type->id]);
    [$alpha, $tie, $zulu, $unlinked] = $activities->all();

    $related = match ($sort) {
        'organization' => Organization::class,
        'program' => Program::class,
        'project' => Project::class,
    };
    $names = collect(['Alpha', 'Mid', 'Zulu'])->map(fn ($name) => $related::factory()->create(['name' => $name]));
    foreach ([$alpha, $tie, $zulu] as $index => $activity) {
        $records = $index === 2 ? [$names[2]->id] : [$names[1]->id, $names[0]->id];
        if ($sort === 'project') {
            foreach ($records as $projectId) {
                $program = Program::factory()->create();
                $program->projects()->attach($projectId);
                $activity->programs()->attach($program);
            }
        } else {
            $activity->{$sort === 'organization' ? 'organizations' : 'programs'}()->attach($records);
        }
    }

    foreach (['asc' => [$unlinked->id, $alpha->id, $tie->id, $zulu->id], 'desc' => [$zulu->id, $tie->id, $alpha->id, $unlinked->id]] as $direction => $expected) {
        $response = $this->actingAs($admin)->get(route('activities.index', [
            'activity_type_id' => $type->id, 'sort' => $sort, 'direction' => $direction,
        ]))->assertOk();
        expect($response->viewData('activities')->modelKeys())->toBe($expected);
    }
})->with(['organization', 'program', 'project']);

test('scoped activity index query count stays constant as rows increase', function (string $role) {
    $program = Program::factory()->create();
    $viewer = $role === 'admin' ? createProgramAdmin($program) : createProgramViewer($program);
    $logger = User::factory()->create();
    $program->users()->attach($logger);
    $type = ActivityType::factory()->create();
    $addActivities = function (int $count) use ($program, $logger, $type): void {
        Activity::factory()->count($count)->create(['user_id' => $logger->id, 'activity_type_id' => $type->id])
            ->each(fn (Activity $activity) => $activity->programs()->attach($program));
    };
    $countQueries = function () use ($viewer): int {
        $this->actingAs($viewer->fresh());
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $this->get(route('activities.index'))->assertOk();

            return count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    };

    $addActivities(2);
    $small = $countQueries();
    $addActivities(8);
    expect($countQueries())->toBe($small);
})->with(['admin', 'viewer']);

<?php

use App\Enums\RecentRecordType;
use App\Models\Agreement;
use App\Models\RecentRecord;
use App\Models\User;
use App\Services\RecentRecordService;

function openRecentPanel(User $user, ?string $currentUrl = null)
{
    return test()->actingAs($user)->get(route('recent.panel'), array_filter([
        'HX-Request' => 'true',
        'HX-Current-URL' => $currentUrl,
    ]));
}

test('opening a record page remembers it once and refreshes its time on revisit', function () {
    $admin = createSystemAdmin();
    $agreement = Agreement::factory()->create();

    $this->actingAs($admin)->get(route('agreements.show', $agreement))->assertOk();
    $this->travel(2)->hours();
    $this->get(route('agreements.show', $agreement))->assertOk();

    $rows = RecentRecord::where('user_id', $admin->id)->get();

    expect($rows)->toHaveCount(1)
        ->and($rows->first()->record_type)->toBe(RecentRecordType::Agreement)
        ->and($rows->first()->record_id)->toBe($agreement->id)
        ->and($rows->first()->last_opened_at->isSameMinute(now()))->toBeTrue();
});

test('index, create, edit, and background requests are not remembered', function () {
    $admin = createSystemAdmin();
    $agreement = Agreement::factory()->create();

    $this->actingAs($admin);
    $this->get(route('agreements.index'))->assertOk();
    $this->get(route('agreements.create'))->assertOk();
    $this->get(route('agreements.edit', $agreement))->assertOk();
    $this->get(route('agreements.show', $agreement), ['HX-Request' => 'true'])->assertOk();

    expect(RecentRecord::count())->toBe(0);
});

test('a record the user is not allowed to open is not remembered', function () {
    $member = User::factory()->create();
    $agreement = Agreement::factory()->create();

    $this->actingAs($member)->get(route('agreements.show', $agreement))->assertForbidden();

    expect(RecentRecord::count())->toBe(0);
});

test('only the 30 newest records are kept', function () {
    $user = User::factory()->create();
    $service = app(RecentRecordService::class);

    foreach (Agreement::factory()->count(32)->create() as $agreement) {
        $this->travel(1)->minutes();
        $service->record($user, RecentRecordType::Agreement, $agreement->id);
    }

    expect(RecentRecord::where('user_id', $user->id)->count())->toBe(30)
        ->and(RecentRecord::where('record_id', Agreement::min('id'))->exists())->toBeFalse();
});

test('records older than seven days expire', function () {
    $admin = createSystemAdmin();
    $stale = Agreement::factory()->create();
    $fresh = Agreement::factory()->create();
    RecentRecord::factory()->create([
        'user_id' => $admin->id,
        'record_id' => $stale->id,
        'last_opened_at' => now()->subDays(8),
    ]);
    RecentRecord::factory()->create(['user_id' => $admin->id, 'record_id' => $fresh->id]);

    openRecentPanel($admin)->assertOk()->assertSee($fresh->name)->assertDontSee($stale->name);

    expect(RecentRecord::where('record_id', $stale->id)->exists())->toBeFalse();
});

test('the panel lists the signed-in user\'s records newest first and marks the current one', function () {
    $admin = createSystemAdmin();
    $other = createSystemAdmin();
    [$older, $newer, $theirs] = Agreement::factory()->count(3)->create();
    RecentRecord::factory()->create(['user_id' => $admin->id, 'record_id' => $older->id, 'last_opened_at' => now()->subHour()]);
    RecentRecord::factory()->create(['user_id' => $admin->id, 'record_id' => $newer->id]);
    RecentRecord::factory()->create(['user_id' => $other->id, 'record_id' => $theirs->id]);

    openRecentPanel($admin, route('agreements.show', $newer))
        ->assertOk()
        ->assertSeeInOrder([$newer->name, 'Current', $older->name])
        ->assertDontSee($theirs->name)
        ->assertSee('aria-current="page"', false);
});

test('the panel shows a first-use message when nothing was opened', function () {
    openRecentPanel(createSystemAdmin())->assertOk()->assertSee('Nothing opened recently');
});

test('the panel is only a fragment for background requests', function () {
    $this->actingAs(createSystemAdmin())->get(route('recent.panel'))->assertRedirect(route('dashboard'));
});

test('deleted records are dropped and records the user can no longer view are hidden', function () {
    $member = User::factory()->create();
    $kept = Agreement::factory()->create();
    $revoked = Agreement::factory()->create();
    $deleted = Agreement::factory()->create();
    $kept->users()->sync([$member->id]);
    $revoked->users()->sync([$member->id]);
    $deleted->users()->sync([$member->id]);

    foreach ([$kept, $revoked, $deleted] as $agreement) {
        app(RecentRecordService::class)->record($member, RecentRecordType::Agreement, $agreement->id);
    }

    $revoked->users()->detach($member->id);
    $deletedName = $deleted->name;
    $deleted->delete();

    openRecentPanel($member)
        ->assertOk()
        ->assertSee($kept->name)
        ->assertDontSee($revoked->name)
        ->assertDontSee($deletedName);

    expect(RecentRecord::where('user_id', $member->id)->pluck('record_id')->all())
        ->toContain($kept->id, $revoked->id)
        ->not->toContain($deleted->id);
});

test('clearing recent removes only the signed-in user\'s entries', function () {
    $admin = createSystemAdmin();
    $other = createSystemAdmin();
    RecentRecord::factory()->create(['user_id' => $admin->id]);
    $theirs = RecentRecord::factory()->create(['user_id' => $other->id]);

    $this->actingAs($admin)
        ->delete(route('recent.clear'), [], ['HX-Request' => 'true'])
        ->assertOk()
        ->assertSee('Nothing opened recently');

    expect(RecentRecord::pluck('id')->all())->toBe([$theirs->id]);
});

test('guests cannot read or clear recent records', function () {
    $this->get(route('recent.panel'))->assertRedirect(route('login'));
    $this->delete(route('recent.clear'))->assertRedirect(route('login'));
});

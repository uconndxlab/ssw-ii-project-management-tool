<?php

use App\Models\Activity;
use App\Models\Agreement;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('covered organizations link from both admin home activity lists', function () {
    $admin = createSystemAdmin();
    $agreement = Agreement::factory()->current()->create();
    $agreement->users()->attach($admin);
    $activity = Activity::factory()->create(['user_id' => $admin->id]);
    $activity->agreements()->attach($agreement);
    $organization = Organization::factory()->create(['name' => 'Covered Organization']);
    $activity->organizations()->attach($organization);

    $response = $this->actingAs($admin)->get(route('dashboard'))->assertOk();

    expect(substr_count($response->getContent(), 'href="'.route('organizations.show', $organization).'"'))->toBe(2);
    $response->assertSee('Covered Organization');
    foreach (['myActivities', 'recentActivities'] as $list) {
        expect($response->viewData($list)->first()->relationLoaded('organizations'))->toBeTrue();
    }
});

test('personal activity organizations only link when the viewer can view them', function () {
    $user = User::factory()->create();
    $agreement = Agreement::factory()->current()->create();
    $agreement->users()->attach($user);
    $activity = Activity::factory()->create(['user_id' => $user->id]);
    $activity->agreements()->attach($agreement);
    $visible = Organization::factory()->create(['name' => 'Viewable Covered Organization']);
    $visible->users()->attach($user);
    $restricted = Organization::factory()->create(['name' => 'Restricted Covered Organization']);
    $activity->organizations()->attach([$visible->id, $restricted->id]);

    $queries = [];
    DB::listen(function ($query) use (&$queries) {
        $queries[] = $query->sql;
    });

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('Viewable Covered Organization')
        ->assertSee('Restricted Covered Organization')
        ->assertSee('href="'.route('organizations.show', $visible).'"', false)
        ->assertDontSee('href="'.route('organizations.show', $restricted).'"', false);
    expect($response->viewData('myActivities')->first()->relationLoaded('organizations'))->toBeTrue();
    expect(collect($queries)->filter(fn (string $sql) => str_contains($sql, 'select exists(select * from "organizations"')))->toBeEmpty();
});

test('home activity rows summarize covered organizations after the first three', function () {
    $activity = Activity::factory()->create();
    $organizations = Organization::factory()->count(5)->sequence(
        ['name' => 'Covered Alpha'],
        ['name' => 'Covered Beta'],
        ['name' => 'Covered Gamma'],
        ['name' => 'Covered Delta'],
        ['name' => 'Covered Epsilon'],
    )->create();
    $activity->organizations()->attach($organizations);

    $this->actingAs(createSystemAdmin())->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Covered Alpha')
        ->assertSee('Covered Beta')
        ->assertSee('Covered Gamma')
        ->assertSee('+2 more')
        ->assertDontSee('Covered Delta')
        ->assertDontSee('Covered Epsilon');
});

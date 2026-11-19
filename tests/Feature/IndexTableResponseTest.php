<?php

use App\Models\ActivityType;
use App\Models\Agreement;
use App\Models\ContactFamily;
use App\Models\Organization;
use App\Models\SubActivity;
use App\Models\User;

it('returns filtered and sorted rows in an HTMX table response', function (string $model, string $route, string $variable, string $view) {
    $admin = createSystemAdmin();
    $alpha = $model::factory()->create(['name' => 'Table needle Alpha']);
    $zulu = $model::factory()->create(['name' => 'Table needle Zulu']);
    $model::factory()->create(['name' => 'Excluded row']);

    $response = $this->actingAs($admin)->withHeader('HX-Request', 'true')
        ->get(route($route, ['search' => 'Table needle', 'sort' => 'name', 'direction' => 'desc']));

    $response->assertOk()->assertViewIs($view)
        ->assertViewHas($variable, fn ($rows) => $rows->modelKeys() === [$zulu->id, $alpha->id])
        ->assertSeeInOrder(['Table needle Zulu', 'Table needle Alpha'])
        ->assertDontSee('Excluded row');
})->with([
    'agreements' => [Agreement::class, 'agreements.index', 'agreements', 'agreements.partials.table'],
    'organizations' => [Organization::class, 'organizations.index', 'organizations', 'organizations.partials.table'],
    'activity types' => [ActivityType::class, 'activity-types.index', 'activityTypes', 'admin.activity-types.partials.table'],
    'sub activities' => [SubActivity::class, 'sub-activities.index', 'subActivities', 'admin.sub-activities.partials.table'],
    'users' => [User::class, 'admin.users.index', 'users', 'admin.users.partials.table'],
    'contact families' => [ContactFamily::class, 'contact-families.index', 'contactFamilies', 'admin.contact-families.partials.table'],
]);

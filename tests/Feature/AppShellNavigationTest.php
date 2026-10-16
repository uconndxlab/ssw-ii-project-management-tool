<?php

use App\Enums\AccessProfile;
use App\Models\Agreement;
use App\Models\User;

test('system admins see work links and every admin setup group in the sidebar', function () {
    $response = $this->actingAs(createSystemAdmin())->get(route('dashboard'));

    $response->assertOk()
        ->assertSeeInOrder(['>Work<', '>Home<', '>Agreements<', '>Activities<', '>Reference Data<', '>Certification Setup<', '>People<'], false)
        ->assertDontSee('>Directory<', false);
});

test('members see the directory group instead of admin setup', function () {
    $this->actingAs(User::factory()->create())->get(route('dashboard'))
        ->assertOk()
        ->assertSeeInOrder(['>Work<', '>Agreements<', '>Directory<', '>Organizations<', '>Teams<'], false)
        ->assertDontSee('>Reference Data<', false);
});

test('input users only see home and activities', function () {
    $response = $this->actingAs(User::factory()->create(['access_profile' => AccessProfile::Input]))
        ->get(route('dashboard'));

    $response->assertOk()
        ->assertSee('>Home<', false)
        ->assertSee('>Activities<', false)
        ->assertDontSee('>Agreements<', false)
        ->assertDontSee('>Directory<', false)
        ->assertDontSee('role="search"', false);
});

test('the sidebar highlights the owning section from the route, not the entry point', function () {
    $admin = createSystemAdmin();
    $agreement = Agreement::factory()->create();

    $this->actingAs($admin)->get(route('agreements.index'))
        ->assertSee('href="'.route('agreements.index').'"', false)
        ->assertSee('aria-current="page"', false);

    $html = $this->get(route('agreements.show', $agreement))->getContent();

    expect($html)->toMatch('/class="app-rail-link is-section"\s+href="'.preg_quote(route('agreements.index'), '/').'"[^>]*aria-current="true"/s')
        ->and(substr_count($html, 'app-rail-link is-section'))->toBe(1);
});

test('every page keeps search, Log Activity, the account menu, and the Recent control', function () {
    $this->actingAs(createSystemAdmin())->get(route('dashboard'))
        ->assertOk()
        ->assertSee('role="search"', false)
        ->assertSee('Log Activity')
        ->assertSee('id="userDropdown"', false)
        ->assertSee('data-bs-target="#recentPanel"', false)
        ->assertSee('Search recent records')
        ->assertSee('href="#main"', false);
});

test('guests get no sidebar', function () {
    $this->get(route('login'))->assertOk()->assertDontSee('id="appRail"', false);
});

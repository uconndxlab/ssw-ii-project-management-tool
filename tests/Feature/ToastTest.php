<?php

use App\Models\User;

test('flashed success and error messages render as toasts with their tones', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['success' => 'Agreement <b>saved</b>.', 'error' => 'Could not delete the agreement.'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<template data-toast="success">Agreement &lt;b&gt;saved&lt;/b&gt;.</template>', false)
        ->assertSee('<template data-toast="danger">Could not delete the agreement.</template>', false)
        ->assertDontSee('alert-success', false);
});

test('flashed allocation warnings render as one titled warning toast', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['allocation_warnings' => ['Kansas MRSS is over its target.']])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-toast="warning" data-toast-title="Deliverable allocation needs attention"', false)
        ->assertSee('<li>Kansas MRSS is over its target.</li>', false);
});

test('the login page shows a flashed error once, inline', function () {
    $response = $this->withSession(['error' => 'Your session expired.'])->get(route('login'));

    $response->assertOk()->assertDontSee('data-toast', false);
    expect(substr_count($response->getContent(), 'Your session expired.'))->toBe(1);
});

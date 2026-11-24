<?php

use App\Models\User;

test('flashed success titles are escaped and errors keep their message body', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['success' => 'Agreement "<b>Example</b>" saved', 'error' => 'Could not delete the agreement.'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-toast-title="Agreement &quot;&lt;b&gt;Example&lt;/b&gt;&quot; saved"', false)
        ->assertSee('<template data-toast="danger">Could not delete the agreement.</template>', false)
        ->assertDontSee('alert-success', false);
});

test('a flashed success message renders as a title-only toast', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['success' => 'Team created'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<template data-toast="success" data-toast-title="Team created"></template>', false);
});

test('a success toast keeps a next step as its body', function () {
    $this->actingAs(User::factory()->create())
        ->withSession(['success' => 'Activity duplicated', 'success_detail' => 'Review the copy and save any changes.'])
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('data-toast-title="Activity duplicated">Review the copy and save any changes.</template>', false);
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

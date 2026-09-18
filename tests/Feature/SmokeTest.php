<?php

use App\Models\User;

test('authenticated member can view dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/')->assertOk();
});

test('authenticated system admin can view dashboard', function () {
    $admin = User::factory()->systemAdmin()->create();

    $this->actingAs($admin)->get('/')->assertOk();
});

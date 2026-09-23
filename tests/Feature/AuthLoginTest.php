<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('active member can log in', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'active' => true,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect('/');

    $this->assertAuthenticatedAs($user);
});

test('inactive member is rejected on login', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
        'active' => false,
    ]);

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasErrors(['email' => 'This account is inactive.']);

    $this->assertGuest();
});

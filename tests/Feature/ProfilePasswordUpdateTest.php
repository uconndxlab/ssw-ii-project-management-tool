<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('authenticated user can update their password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user)->put(route('profile.password.update'), [
        'current_password' => 'password',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ])->assertRedirect(route('profile.edit'));

    expect(Hash::check('new-password-123', $user->fresh()->password))->toBeTrue();
});

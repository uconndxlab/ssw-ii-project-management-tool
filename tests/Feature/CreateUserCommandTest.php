<?php

use App\Enums\AccessProfile;
use App\Models\User;

test('user create command persists a member and prints their access label', function () {
    $this->artisan('user:create', [
        '--name' => 'Ada Lovelace',
        '--email' => 'ada@example.com',
        '--password' => 'password123',
        '--profile' => AccessProfile::Member->value,
    ])->assertSuccessful();

    $user = User::query()->where('email', 'ada@example.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('Ada Lovelace')
        ->and($user->accessLabel())->toBe('User');
});

<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;

test('password reset notification url contains the user email', function () {
    $user = User::factory()->create();
    $notification = new ResetPassword('test-token');
    $mail = $notification->toMail($user);

    expect($mail->actionUrl)->toContain(urlencode($user->email));
});

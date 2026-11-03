<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;

test('password reset email uses the branded template with a link carrying the user email', function () {
    $user = User::factory()->create(['name' => 'Dana Example']);
    $mail = (new ResetPassword('test-token'))->toMail($user);

    expect($mail->markdown)->toBe('mail.password-reset')
        ->and($mail->viewData['url'])->toContain('test-token')->toContain(urlencode($user->email))
        ->and((string) $mail->render())->toContain('Hi Dana,')->toContain('expires in 60 minutes');
});

<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

// Password reset is switched off (404) unless a sender address is configured.
beforeEach(fn () => config(['mail.from.address' => 'support@example.com']));

test('requesting a reset link for an account emails it and shows the confirmation', function () {
    $user = User::factory()->create();
    Notification::fake([ResetPassword::class]);

    $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => $user->email])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('reset_link_sent', true);

    Notification::assertSentTo($user, ResetPassword::class);
    $this->get('/forgot-password')->assertSee('Check your email')->assertSee($user->email)->assertDontSee('Send reset link');
});

test('an unknown email gets the same confirmation, so it cannot reveal who has an account', function () {
    $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => 'nobody@example.com'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHasNoErrors()
        ->assertSessionHas('reset_link_sent', true);
});

test('a request that loses the token race to a simultaneous one still gets the confirmation', function () {
    Password::shouldReceive('sendResetLink')->once()->andThrow(
        new UniqueConstraintViolationException('pgsql', 'insert into "password_reset_tokens"', [], new Exception('duplicate key'))
    );

    $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => 'someone@example.com'])
        ->assertRedirect('/forgot-password')
        ->assertSessionHas('reset_link_sent', true);
});

test('a malformed email is rejected without the confirmation', function () {
    $this->from('/forgot-password')
        ->post('/forgot-password', ['email' => 'not-an-email'])
        ->assertSessionHasErrors(['email' => 'The email field must be a valid email address.'])
        ->assertSessionMissing('reset_link_sent');
});

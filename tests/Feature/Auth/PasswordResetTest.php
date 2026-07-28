<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('reset password link screen can be rendered', function (): void {
    $this->get('/testcom/forgot-password')->assertStatus(200);
});

test('requesting a reset link for a known email sends the notification after the response is deferred', function (): void {
    // Regression: App\Http\Controllers\Auth\PasswordResetLinkController::store()
    // now passes Password::sendResetLink() a callback that defers the actual
    // send via dispatch(...)->afterResponse() instead of the default
    // $user->sendPasswordResetNotification($token) - the token creation and
    // resulting $status stay synchronous (this proves that), only the
    // notification itself is deferred.
    $community = newCommunity();
    $user = TestLdap::member($community);

    Notification::fake();

    $this->post(route('password.email', ['realm' => $community->getShortCode()]), [
        'mail' => $user->email,
    ])
        ->assertRedirect()
        ->assertSessionHas('status', __('passwords.sent'));

    Notification::assertSentTo(
        User::where('email', $user->email)->where('realm', $community->getShortCode())->firstOrFail(),
        ResetPassword::class
    );
});

test('requesting a reset link for an unknown email does not send a notification', function (): void {
    $community = newCommunity();

    Notification::fake();

    $this->post(route('password.email', ['realm' => $community->getShortCode()]), [
        'mail' => 'nobody-'.bin2hex(random_bytes(4)).'@example.test',
    ])->assertSessionHasErrors('mail');

    Notification::assertNothingSent();
});

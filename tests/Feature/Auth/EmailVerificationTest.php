<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

test('email verification screen can be rendered', function (): void {
    $community = newCommunity();
    $user = User::factory()->create([
        'email_verified_at' => null,
        'realm' => $community->getShortCode(),
    ]);

    $response = $this->actingAs($user)->get("/{$community->getShortCode()}/verify-email");

    $response->assertStatus(200);
});

test('email can be verified', function (): void {
    $community = newCommunity();
    $user = User::factory()->create([
        'email_verified_at' => null,
        'realm' => $community->getShortCode(),
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['realm' => $community->getShortCode(), 'id' => $user->id, 'hash' => sha1((string) $user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(RouteServiceProvider::home($community->getShortCode()).'?verified=1');
});

test('email is not verified with invalid hash', function (): void {
    $community = newCommunity();
    $user = User::factory()->create([
        'email_verified_at' => null,
        'realm' => $community->getShortCode(),
    ]);

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['realm' => $community->getShortCode(), 'id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('resending the verification email dispatches the notification after the response is deferred', function (): void {
    // Regression: App\Http\Controllers\Auth\EmailVerificationNotificationController::store()
    // defers the actual send via dispatch(...)->afterResponse() - this only
    // proves the notification still goes out, not that it's literally
    // deferred (Notification::fake() intercepts before any queueing/timing
    // distinction would be observable in a test).
    $community = newCommunity();
    $user = User::factory()->create([
        'email_verified_at' => null,
        'realm' => $community->getShortCode(),
    ]);

    Notification::fake();

    $this->actingAs($user)
        ->post(route('verification.send', ['realm' => $community->getShortCode()]))
        ->assertRedirect()
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);
});

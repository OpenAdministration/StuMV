<?php

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
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

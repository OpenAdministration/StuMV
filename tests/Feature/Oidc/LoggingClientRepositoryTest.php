<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Bridge\ClientRepository;
use Laravel\Passport\ClientRepository as PassportClientRepository;

uses(RefreshDatabase::class);

test('an unknown client_id is logged', function (): void {
    Log::spy();

    $repository = resolve(ClientRepository::class);

    expect($repository->validateClient('does-not-exist', 'whatever', 'authorization_code'))->toBeFalse();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'OIDC client authentication failed: unknown or revoked client_id'
            && $context['client_id'] === 'does-not-exist');
});

test('a missing client_secret is logged', function (): void {
    $community = newCommunity();
    $client = resolve(PassportClientRepository::class)->createAuthorizationCodeGrantClient('Test Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();

    Log::spy();

    $repository = resolve(ClientRepository::class);

    expect($repository->validateClient($client->id, null, 'authorization_code'))->toBeFalse();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $message === 'OIDC client authentication failed: no client_secret was received'
            && $context['client_id'] === $client->id);
});

test('a mismatched client_secret is logged, without logging the secret itself', function (): void {
    $community = newCommunity();
    $client = resolve(PassportClientRepository::class)->createAuthorizationCodeGrantClient('Test Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();

    Log::spy();

    $repository = resolve(ClientRepository::class);

    expect($repository->validateClient($client->id, 'the-wrong-secret', 'authorization_code'))->toBeFalse();

    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(function (string $message, array $context) use ($client): bool {
            expect($context)->not->toContain('the-wrong-secret')
                ->and($context)->not->toContain($client->plainSecret);

            return $message === 'OIDC client authentication failed: client_secret did not match'
                && $context['client_id'] === $client->id;
        });
});

test('a correct client_secret is not logged at all', function (): void {
    $community = newCommunity();
    $client = resolve(PassportClientRepository::class)->createAuthorizationCodeGrantClient('Test Client', ['https://example.test/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();

    Log::spy();

    $repository = resolve(ClientRepository::class);

    expect($repository->validateClient($client->id, $client->plainSecret, 'authorization_code'))->toBeTrue();

    Log::shouldNotHaveReceived('warning');
});

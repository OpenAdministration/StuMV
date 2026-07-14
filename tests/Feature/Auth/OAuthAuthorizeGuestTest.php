<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;

/*
 * Regression guard for the StuFis SSO login flow.
 *
 * Passport 13's authorize endpoint (GET /oauth/authorize carries only `web`,
 * not `auth`) throws an AuthenticationException for guests. Laravel 13 removed
 * the automatic route('login') fallback in the exception handler's
 * unauthenticated(), so an AuthenticationException without a redirect target
 * now yields a bare, empty 401 instead of a login redirect - which silently
 * broke SSO: a visitor without a StuMV session hit a dead 401 and never saw the
 * login form. AppServiceProvider registers AuthenticationException::redirectUsing
 * to restore the redirect; this test fails loudly if that is ever dropped or a
 * future upgrade regresses the behaviour again.
 */
uses(RefreshDatabase::class);

test('a guest hitting the oauth authorize endpoint is redirected to login, not given a 401', function (): void {
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient(
        'Regression SSO Client',
        ['https://client.test/callback'],
    );

    $response = $this->get('/oauth/authorize?'.http_build_query([
        'client_id' => $client->getKey(),
        'redirect_uri' => 'https://client.test/callback',
        'response_type' => 'code',
        'scope' => 'profile',
    ]));

    $response->assertRedirect(route('login'));
});

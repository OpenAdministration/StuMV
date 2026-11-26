<?php

use App\Models\ActivityLog;
use App\Models\PassportClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\AuthCode;
use Laravel\Passport\ClientRepository;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('signing in to StuMV is logged with the person as actor', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    $password = 'Aa1!'.bin2hex(random_bytes(6));
    $ldap = $member->ldap();
    $ldap->setAttribute('userPassword', '{ARGON2}'.password_hash($password, PASSWORD_ARGON2ID));
    $ldap->save();

    $this->post('/'.$community->getShortCode().'/login', ['uid' => $member->username, 'password' => $password])
        ->assertSessionHasNoErrors();

    expect(ActivityLog::sole()->only(['action', 'realm', 'actor_type', 'actor_id', 'subject_label']))->toBe([
        'action' => 'login.app',
        'realm' => $community->getShortCode(),
        'actor_type' => 'user',
        'actor_id' => $member->uid,
        'subject_label' => config('app.name'),
    ]);
});

test('signing in to an OIDC client is logged once per authorization, with the client as subject', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    /** @var PassportClient $client */
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('Wiki', ['https://wiki.example.test/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();

    // as Laravel\Passport\Bridge\AuthCodeRepository stores it
    (new AuthCode)->forceFill([
        'id' => Str::random(80),
        'user_id' => $member->id,
        'client_id' => $client->id,
        'scopes' => '[]',
        'revoked' => false,
        'expires_at' => now()->addMinutes(10),
    ])->save();

    expect(ActivityLog::sole()->only(['action', 'realm', 'actor_id', 'subject_id', 'subject_label']))->toBe([
        'action' => 'login.oidc_client',
        'realm' => $community->getShortCode(),
        'actor_id' => $member->uid,
        'subject_id' => $client->id,
        'subject_label' => 'Wiki',
    ]);
});

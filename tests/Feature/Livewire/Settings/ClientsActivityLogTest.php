<?php

use App\Livewire\Settings\Api\EditApiClient;
use App\Livewire\Settings\Api\ListApiClients;
use App\Livewire\Settings\Api\NewApiClient;
use App\Livewire\Settings\IdentityProvider\EditIdentityProvider;
use App\Livewire\Settings\IdentityProvider\ListIdentityProviders;
use App\Livewire\Settings\IdentityProvider\NewIdentityProvider;
use App\Livewire\Settings\Oidc\EditOidcClient;
use App\Livewire\Settings\Oidc\ListOidcClients;
use App\Models\ActivityLog;
use App\Models\PassportClient;
use App\Models\RealmIdentityProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\ClientRepository;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('an API client is logged through its lifecycle without its secret', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);

    Livewire::test(NewApiClient::class, ['realm' => $community])
        ->set('name', 'Website')
        ->set('scopes', ['committees'])
        ->call('save');
    $client = PassportClient::where('community_uid', $community->getShortCode())->sole();
    Livewire::test(EditApiClient::class, ['realm' => $community, 'client' => $client])
        ->set('name', 'Homepage')
        ->set('scopes', ['committees', 'users'])
        ->call('save');
    Livewire::test(ListApiClients::class, ['realm' => $community])
        ->call('revokePrepare', $client->id)
        ->call('revokeCommit')
        ->call('deletePrepare', $client->id)
        ->call('deleteCommit');

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['api_client.created', 'api_client.updated', 'api_client.revoked', 'api_client.deleted'])
        ->and($logs->pluck('realm')->unique()->all())->toBe([$community->getShortCode()])
        ->and($logs->pluck('subject_id')->unique()->all())->toBe([$client->id])
        ->and($logs[0]->details)->not->toHaveKey('secret')
        ->and($logs[1]->details)->toBe([
            'name' => ['old' => 'Website', 'new' => 'Homepage'],
            'scopes' => ['old' => ['committees'], 'new' => ['committees', 'users']],
        ])
        ->and($logs[2]->details)->toBeNull();
});

test('switching an OIDC client\'s authentication and regenerating its secret are logged, the secret is not', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'scopes' => ['openid']])->save();
    $client->refresh();
    actingAsAdmin($community);

    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client])
        ->set('disableClientAuthentication', true)
        ->call('save');
    Livewire::test(EditOidcClient::class, ['realm' => $community, 'client' => $client->fresh()])
        ->set('disableClientAuthentication', false)
        ->call('save')
        ->call('regenerateSecret');
    Livewire::test(ListOidcClients::class, ['realm' => $community])
        ->call('revokePrepare', $client->id)
        ->call('revokeCommit');

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe(['oidc_client.updated', 'oidc_client.updated', 'oidc_client.secret_regenerated', 'oidc_client.revoked'])
        ->and($logs[0]->details)->toBe(['client_authentication' => ['old' => true, 'new' => false]])
        ->and($logs[1]->details)->toBe(['client_authentication' => ['old' => false, 'new' => true]])
        ->and(json_encode($logs->pluck('details')))->not->toContain('secret"');
});

test('identity providers are logged with their mappings, the client secret only as changed', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $group = TestLdap::makeGroup($community);
    actingAsAdmin($community);

    Livewire::test(NewIdentityProvider::class, ['realm' => $community])
        ->set('name', 'Uni')
        ->set('issuer', 'https://idp.example.org')
        ->set('client_id', 'stumv')
        ->set('client_secret', 'very-secret')
        ->call('save')
        ->assertHasNoErrors();
    $provider = RealmIdentityProvider::where('realm', $community->getShortCode())->sole();
    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->set('client_secret', 'even-more-secret')
        ->call('save')
        ->set('new_external_group', 'stura-member')
        ->set('new_committee_dn', $committee->getDn())
        ->set('new_role_cn', $role->getFirstAttribute('cn'))
        ->call('addMapping')
        ->set('new_group_external_group', 'stura-all')
        ->set('new_group_dn', $group->getDn())
        ->call('addGroupMapping');
    Livewire::test(ListIdentityProviders::class, ['realm' => $community])
        ->call('deletePrepare', $provider->id)
        ->call('deleteCommit');

    $logs = ActivityLog::orderBy('id')->get();
    expect($logs->pluck('action')->all())->toBe([
        'identity_provider.created',
        'identity_provider.updated',
        'identity_provider.role_mapping_added',
        'identity_provider.group_mapping_added',
        'identity_provider.deleted',
    ])
        ->and($logs->pluck('subject_label')->unique()->all())->toBe(['Uni'])
        ->and($logs[0]->details['client_secret'])->toBe('[redacted]')
        ->and($logs[1]->details)->toBe(['client_secret' => '[redacted]'])
        ->and($logs[2]->details)->toBe(['external_group' => 'stura-member', 'committee_dn' => $committee->getDn(), 'role_cn' => $role->getFirstAttribute('cn')])
        ->and($logs[4]->details['role_mappings'])->toHaveCount(1)
        ->and($logs[4]->details['group_mappings'])->toBe([['external_group' => 'stura-all', 'group_dn' => $group->getDn()]])
        ->and(json_encode($logs->pluck('details')))->not->toContain('secret-')->not->toContain('very-secret');
});

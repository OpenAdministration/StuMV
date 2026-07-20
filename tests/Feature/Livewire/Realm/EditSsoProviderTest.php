<?php

use App\Livewire\Realm\EditSsoProvider;
use App\Models\SsoProviderRoleMapping;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the edit form is pre-filled with the provider\'s current values', function (): void {
    $community = newCommunity();
    $provider = makeSsoProvider($community->getShortCode(), 'My University');
    actingAsAdmin($community);

    Livewire::test(EditSsoProvider::class, ['realm' => $community, 'provider' => $provider])
        ->assertSet('name', 'My University')
        ->assertSet('issuer', 'https://idp.example.test')
        ->assertSet('client_id', 'client-id')
        ->assertSet('client_secret', 'client-secret')
        ->assertSet('groups_claim', 'groups')
        ->assertSet('enabled', true);
});

test('a provider\'s settings can be updated', function (): void {
    $community = newCommunity();
    $provider = makeSsoProvider($community->getShortCode());
    actingAsAdmin($community);

    Livewire::test(EditSsoProvider::class, ['realm' => $community, 'provider' => $provider])
        ->set('name', 'Renamed IdP')
        ->set('client_secret', 'new-secret')
        ->set('enabled', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.sso-providers', ['realm' => $community->getShortCode()]));

    $provider->refresh();

    expect($provider->name)->toBe('Renamed IdP')
        ->and($provider->client_secret)->toBe('new-secret')
        ->and($provider->enabled)->toBeFalse();
});

test('another realm\'s identity provider cannot be opened through this edit page', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $provider = makeSsoProvider($otherCommunity->getShortCode());
    actingAsAdmin($community);

    Livewire::test(EditSsoProvider::class, ['realm' => $community, 'provider' => $provider])
        ->assertStatus(404);
});

test('a non-admin cannot edit an identity provider', function (): void {
    $community = newCommunity();
    $provider = makeSsoProvider($community->getShortCode());
    actingAsModerator($community);

    $this->get(route('realms.sso-providers.edit', ['realm' => $community->getShortCode(), 'provider' => $provider->id]))->assertForbidden();
});

test('a role mapping can be added and lists the committee/role it targets', function (): void {
    $community = newCommunity();
    $provider = makeSsoProvider($community->getShortCode());
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    actingAsAdmin($community);

    Livewire::test(EditSsoProvider::class, ['realm' => $community, 'provider' => $provider])
        ->set('new_external_group', 'stura-member')
        ->set('new_committee_dn', $committee->getDn())
        ->set('new_role_cn', $role->getFirstAttribute('cn'))
        ->call('addMapping')
        ->assertHasNoErrors();

    $mapping = SsoProviderRoleMapping::where('provider_id', $provider->id)->firstOrFail();

    expect($mapping->external_group)->toBe('stura-member')
        ->and($mapping->committee_dn)->toBe($committee->getDn())
        ->and($mapping->role_cn)->toBe($role->getFirstAttribute('cn'));
});

test('adding a mapping requires an external group, committee and role', function (): void {
    $community = newCommunity();
    $provider = makeSsoProvider($community->getShortCode());
    actingAsAdmin($community);

    Livewire::test(EditSsoProvider::class, ['realm' => $community, 'provider' => $provider])
        ->call('addMapping')
        ->assertHasErrors(['new_external_group' => 'required', 'new_committee_dn' => 'required', 'new_role_cn' => 'required']);
});

test('a role mapping can be deleted', function (): void {
    $community = newCommunity();
    $provider = makeSsoProvider($community->getShortCode());
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $mapping = $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);
    actingAsAdmin($community);

    Livewire::test(EditSsoProvider::class, ['realm' => $community, 'provider' => $provider])
        ->call('deleteMappingPrepare', $mapping->id)
        ->call('deleteMappingCommit');

    expect(SsoProviderRoleMapping::find($mapping->id))->toBeNull();
});

test('a role mapping is scoped to its own provider - another provider\'s mapping cannot be deleted through this page', function (): void {
    $community = newCommunity();
    $provider = makeSsoProvider($community->getShortCode(), 'Provider A');
    $otherProvider = makeSsoProvider($community->getShortCode(), 'Provider B');
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $mapping = $otherProvider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);
    actingAsAdmin($community);

    Livewire::test(EditSsoProvider::class, ['realm' => $community, 'provider' => $provider])
        ->call('deleteMappingPrepare', $mapping->id);
})->throws(ModelNotFoundException::class);

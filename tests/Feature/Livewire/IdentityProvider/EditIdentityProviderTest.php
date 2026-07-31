<?php

use App\Ldap\Committee;
use App\Ldap\Group;
use App\Livewire\IdentityProvider\EditIdentityProvider;
use App\Models\IdentityProviderGroupMapping;
use App\Models\IdentityProviderRoleMapping;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the edit form is pre-filled with the provider\'s current values, but never the client secret', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode(), 'My University');
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->assertSet('name', 'My University')
        ->assertSet('issuer', 'https://idp.example.test')
        ->assertSet('client_id', 'client-id')
        ->assertSet('client_secret', '')
        ->assertSet('groups_claim', 'groups')
        ->assertSet('enabled', true);
});

test('a provider\'s settings can be updated', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->set('name', 'Renamed IdP')
        ->set('client_secret', 'new-secret')
        ->set('enabled', false)
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.identity-providers', ['realm' => $community->getShortCode()]));

    $provider->refresh();

    expect($provider->name)->toBe('Renamed IdP')
        ->and($provider->client_secret)->toBe('new-secret')
        ->and($provider->enabled)->toBeFalse();
});

test('leaving the client secret blank keeps the current secret unchanged', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->set('name', 'Renamed IdP')
        ->call('save')
        ->assertHasNoErrors();

    $provider->refresh();

    expect($provider->name)->toBe('Renamed IdP')
        ->and($provider->client_secret)->toBe('client-secret');
});

test('another realm\'s identity provider cannot be opened through this edit page', function (): void {
    $community = newCommunity();
    $otherCommunity = newCommunity();
    $provider = makeIdentityProvider($otherCommunity->getShortCode());
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->assertStatus(404);
});

test('a non-admin cannot edit an identity provider', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    actingAsModerator($community);

    $this->get(route('realms.identity-providers.edit', ['realm' => $community->getShortCode(), 'provider' => $provider->id]))->assertForbidden();
});

test('an existing mapping shows the committee/role LDAP descriptions, linked to their respective pages', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->assertSee($committee->getFirstAttribute('description'))
        ->assertSee($role->getFirstAttribute('description'))
        ->assertSeeHtml(route('committees.roles', ['realm' => $community->getShortCode(), 'ou' => $committee->getFirstAttribute('ou')]))
        ->assertSeeHtml(route('committees.roles.members', ['realm' => $community->getShortCode(), 'ou' => $committee->getFirstAttribute('ou'), 'cn' => $role->getFirstAttribute('cn')]));
});

test('a mapping whose committee no longer exists in LDAP falls back to showing the raw DN, without a link', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => Committee::dnFrom($community->getShortCode(), 'gone'),
        'role_cn' => 'gone-role',
    ]);
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->assertSee('gone-role')
        ->assertHasNoErrors();
});

test('a role mapping can be added and lists the committee/role it targets', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->set('new_external_group', 'stura-member')
        ->set('new_committee_dn', $committee->getDn())
        ->set('new_role_cn', $role->getFirstAttribute('cn'))
        ->call('addMapping')
        ->assertHasNoErrors();

    $mapping = IdentityProviderRoleMapping::where('provider_id', $provider->id)->firstOrFail();

    expect($mapping->external_group)->toBe('stura-member')
        ->and($mapping->committee_dn)->toBe($committee->getDn())
        ->and($mapping->role_cn)->toBe($role->getFirstAttribute('cn'));
});

test('the committee select for role mappings only offers committees that have at least one role', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $committeeWithRole = TestLdap::makeCommittee($community);
    TestLdap::makeRole($committeeWithRole);
    $committeeWithoutRole = TestLdap::makeCommittee($community);
    actingAsAdmin($community);

    $committees = Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->viewData('committees');

    $committeeDns = $committees->map(fn (Committee $committee): string => $committee->getDn());

    expect($committeeDns)
        ->toContain($committeeWithRole->getDn())
        ->not->toContain($committeeWithoutRole->getDn());
});

test('adding a mapping requires an external group, committee and role', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->call('addMapping')
        ->assertHasErrors(['new_external_group' => 'required', 'new_committee_dn' => 'required', 'new_role_cn' => 'required']);
});

test('a role mapping can be deleted', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $mapping = $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->call('deleteMappingPrepare', $mapping->id)
        ->call('deleteMappingCommit');

    expect(IdentityProviderRoleMapping::find($mapping->id))->toBeNull();
});

test('a role mapping is scoped to its own provider - another provider\'s mapping cannot be deleted through this page', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode(), 'Provider A');
    $otherProvider = makeIdentityProvider($community->getShortCode(), 'Provider B');
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $mapping = $otherProvider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->call('deleteMappingPrepare', $mapping->id);
})->throws(ModelNotFoundException::class);

test('a group mapping can be added and lists the group it targets', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $group = TestLdap::makeGroup($community);
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->set('new_group_external_group', 'stura-member')
        ->set('new_group_dn', $group->getDn())
        ->call('addGroupMapping')
        ->assertHasNoErrors();

    $mapping = IdentityProviderGroupMapping::where('provider_id', $provider->id)->firstOrFail();

    expect($mapping->external_group)->toBe('stura-member')
        ->and($mapping->group_dn)->toBe($group->getDn());
});

test('adding a group mapping requires an external group and a group', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->call('addGroupMapping')
        ->assertHasErrors(['new_group_external_group' => 'required', 'new_group_dn' => 'required']);
});

test('an existing group mapping shows the group\'s LDAP description, linked to its members page', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $group = TestLdap::makeGroup($community);
    $provider->groupMappings()->create([
        'external_group' => 'stura-member',
        'group_dn' => $group->getDn(),
    ]);
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->assertSee($group->getFirstAttribute('description'))
        ->assertSeeHtml(route('realms.groups.members', ['realm' => $community->getShortCode(), 'cn' => $group->getFirstAttribute('cn')]));
});

test('a group mapping whose group no longer exists in LDAP falls back to showing the raw DN, without a link', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->groupMappings()->create([
        'external_group' => 'stura-member',
        'group_dn' => Group::dnFrom($community->getShortCode(), 'gone'),
    ]);
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->assertSee('stura-member')
        ->assertHasNoErrors();
});

test('a group mapping can be deleted', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode());
    $group = TestLdap::makeGroup($community);
    $mapping = $provider->groupMappings()->create([
        'external_group' => 'stura-member',
        'group_dn' => $group->getDn(),
    ]);
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->call('deleteGroupMappingPrepare', $mapping->id)
        ->call('deleteGroupMappingCommit');

    expect(IdentityProviderGroupMapping::find($mapping->id))->toBeNull();
});

test('a group mapping is scoped to its own provider - another provider\'s mapping cannot be deleted through this page', function (): void {
    $community = newCommunity();
    $provider = makeIdentityProvider($community->getShortCode(), 'Provider A');
    $otherProvider = makeIdentityProvider($community->getShortCode(), 'Provider B');
    $group = TestLdap::makeGroup($community);
    $mapping = $otherProvider->groupMappings()->create([
        'external_group' => 'stura-member',
        'group_dn' => $group->getDn(),
    ]);
    actingAsAdmin($community);

    Livewire::test(EditIdentityProvider::class, ['realm' => $community, 'provider' => $provider])
        ->call('deleteGroupMappingPrepare', $mapping->id);
})->throws(ModelNotFoundException::class);

<?php

use App\Support\IdentityProviderGroupSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a mapped external group grants the corresponding LDAP group membership', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $group = TestLdap::makeGroup($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->groupMappings()->create([
        'external_group' => 'stura-member',
        'group_dn' => $group->getDn(),
    ]);

    resolve(IdentityProviderGroupSync::class)->apply($provider, $user->username, ['groups' => ['stura-member']]);

    $group->refresh();
    expect($group->members()->get()->map(fn ($u) => $u->getDn())->all())->toContain($user->ldap()->getDn());
});

test('an unmapped external group is ignored', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $group = TestLdap::makeGroup($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->groupMappings()->create([
        'external_group' => 'stura-member',
        'group_dn' => $group->getDn(),
    ]);

    resolve(IdentityProviderGroupSync::class)->apply($provider, $user->username, ['groups' => ['some-other-group']]);

    $group->refresh();
    expect($group->members()->get())->toBeEmpty();
});

test('applying the same groups twice does not attach the same member twice', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $group = TestLdap::makeGroup($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->groupMappings()->create([
        'external_group' => 'stura-member',
        'group_dn' => $group->getDn(),
    ]);

    $sync = resolve(IdentityProviderGroupSync::class);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);

    $group->refresh();
    expect($group->members()->get())->toHaveCount(1);
});

test('a missing groups claim is a no-op', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $group = TestLdap::makeGroup($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->groupMappings()->create([
        'external_group' => 'stura-member',
        'group_dn' => $group->getDn(),
    ]);

    resolve(IdentityProviderGroupSync::class)->apply($provider, $user->username, []);

    $group->refresh();
    expect($group->members()->get())->toBeEmpty();
});

test('a custom groups_claim name is honoured', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $group = TestLdap::makeGroup($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->update(['groups_claim' => 'memberOf']);
    $provider->groupMappings()->create([
        'external_group' => 'stura-member',
        'group_dn' => $group->getDn(),
    ]);

    resolve(IdentityProviderGroupSync::class)->apply($provider, $user->username, ['memberOf' => ['stura-member']]);

    $group->refresh();
    expect($group->members()->get())->toHaveCount(1);
});

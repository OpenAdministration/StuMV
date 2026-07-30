<?php

use App\Models\IdentityProviderGroupGrant;
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

test('a membership this sync granted is detached once its external group is no longer claimed', function (): void {
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
    $sync->apply($provider, $user->username, ['groups' => ['some-other-group']]);

    $group->refresh();
    expect($group->members()->get())->toBeEmpty();
    expect(IdentityProviderGroupGrant::where('provider_id', $provider->id)->where('username', $user->username)->count())->toBe(0);
});

test('re-granting after a detach re-attaches the membership', function (): void {
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
    $sync->apply($provider, $user->username, ['groups' => []]);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);

    $group->refresh();
    expect($group->members()->get()->map(fn ($u) => $u->getDn())->all())->toContain($user->ldap()->getDn());
});

test('a membership that pre-dates this sync is never detached, even after its external group later stops being claimed', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $group = TestLdap::makeGroup($community);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->groupMappings()->create([
        'external_group' => 'stura-member',
        'group_dn' => $group->getDn(),
    ]);

    // Membership already exists before this sync ever runs - e.g. granted
    // manually, or via the independent role-derived ldap:sync-groups path.
    $group->members()->attach($user->ldap());

    $sync = resolve(IdentityProviderGroupSync::class);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);

    // No grant row should have been recorded for a membership we didn't
    // ourselves establish.
    expect(IdentityProviderGroupGrant::where('provider_id', $provider->id)->where('username', $user->username)->count())->toBe(0);

    $sync->apply($provider, $user->username, ['groups' => []]);

    $group->refresh();
    expect($group->members()->get()->map(fn ($u) => $u->getDn())->all())->toContain($user->ldap()->getDn());
});

test('a membership granted by a different provider is not detached by this provider\'s sync', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $group = TestLdap::makeGroup($community);
    $providerA = makeIdentityProvider($community->getShortCode(), 'Provider A');
    $providerB = makeIdentityProvider($community->getShortCode(), 'Provider B');
    $providerA->groupMappings()->create([
        'external_group' => 'stura-member',
        'group_dn' => $group->getDn(),
    ]);

    $sync = resolve(IdentityProviderGroupSync::class);
    $sync->apply($providerA, $user->username, ['groups' => ['stura-member']]);
    $sync->apply($providerB, $user->username, ['groups' => []]);

    $group->refresh();
    expect($group->members()->get()->map(fn ($u) => $u->getDn())->all())->toContain($user->ldap()->getDn());
});

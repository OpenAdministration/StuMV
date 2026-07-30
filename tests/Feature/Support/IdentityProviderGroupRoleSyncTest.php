<?php

use App\Models\RoleMembership;
use App\Support\IdentityProviderGroupRoleSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a mapped external group grants the corresponding role', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    resolve(IdentityProviderGroupRoleSync::class)->apply($provider, $user->username, ['groups' => ['stura-member']]);

    expect(RoleMembership::where('username', $user->username)
        ->where('role_cn', $role->getFirstAttribute('cn'))
        ->where('committee_dn', $committee->getDn())
        ->count())->toBe(1);
});

test('an unmapped external group is ignored', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    resolve(IdentityProviderGroupRoleSync::class)->apply($provider, $user->username, ['groups' => ['some-other-group']]);

    expect(RoleMembership::where('username', $user->username)->count())->toBe(0);
});

test('applying the same groups twice does not create a duplicate membership', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    $sync = resolve(IdentityProviderGroupRoleSync::class);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);

    expect(RoleMembership::where('username', $user->username)->count())->toBe(1);
});

test('a missing groups claim is a no-op', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $provider = makeIdentityProvider($community->getShortCode());

    resolve(IdentityProviderGroupRoleSync::class)->apply($provider, $user->username, []);

    expect(RoleMembership::where('username', $user->username)->count())->toBe(0);
});

test('a custom groups_claim name is honoured', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->update(['groups_claim' => 'memberOf']);
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    resolve(IdentityProviderGroupRoleSync::class)->apply($provider, $user->username, ['memberOf' => ['stura-member']]);

    expect(RoleMembership::where('username', $user->username)->count())->toBe(1);
});

test('a role this sync granted today is removed outright if revoked the same day', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    $sync = resolve(IdentityProviderGroupRoleSync::class);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);
    $sync->apply($provider, $user->username, ['groups' => ['some-other-group']]);

    // "Ended the day before" would mean until < from for a grant made today -
    // a reversed range isActive() reads as still active (see the sync's doc
    // comment) - so a same-day grant-then-revoke has no valid historical
    // window to record and is deleted outright instead.
    expect(RoleMembership::where('username', $user->username)
        ->where('role_cn', $role->getFirstAttribute('cn'))
        ->where('committee_dn', $committee->getDn())
        ->count())->toBe(0);
});

test('a role this sync granted on an earlier day is revoked effective the day before this login', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    RoleMembership::create([
        'role_cn' => $role->getFirstAttribute('cn'),
        'committee_dn' => $committee->getDn(),
        'realm' => $community->getShortCode(),
        'username' => $user->username,
        'from' => now()->subDays(10)->toDateString(),
        'until' => null,
        'decided' => now()->subDays(10)->toDateString(),
        'comment' => __('identity_providers.auto_assigned_comment', ['provider' => $provider->name]),
        'identity_provider_id' => $provider->id,
    ]);

    resolve(IdentityProviderGroupRoleSync::class)->apply($provider, $user->username, ['groups' => ['some-other-group']]);

    $membership = RoleMembership::where('username', $user->username)
        ->where('role_cn', $role->getFirstAttribute('cn'))
        ->where('committee_dn', $committee->getDn())
        ->first();

    expect($membership)->not->toBeNull()
        ->and($membership->until->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($membership->isActive())->toBeFalse();
});

test('a revoked role is reactivated once its external group reappears in the claim', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    $sync = resolve(IdentityProviderGroupRoleSync::class);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);
    $sync->apply($provider, $user->username, ['groups' => []]);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);

    $membership = RoleMembership::where('username', $user->username)
        ->where('role_cn', $role->getFirstAttribute('cn'))
        ->where('committee_dn', $committee->getDn())
        ->first();

    expect($membership->isActive())->toBeTrue();
});

test('a manually granted role is never revoked, even if a matching mapping later stops being claimed', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeIdentityProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    $manual = RoleMembership::create([
        'role_cn' => $role->getFirstAttribute('cn'),
        'committee_dn' => $committee->getDn(),
        'realm' => $community->getShortCode(),
        'username' => $user->username,
        'from' => now()->toDateString(),
        'until' => null,
        'decided' => now()->toDateString(),
        'comment' => 'Manually granted by an admin',
    ]);

    $sync = resolve(IdentityProviderGroupRoleSync::class);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);

    // The claim matched the mapping, but firstOrNew must have found the
    // manual row rather than creating a second one, and must not have
    // stamped it as ours.
    expect(RoleMembership::where('username', $user->username)->count())->toBe(1);
    expect($manual->fresh()->identity_provider_id)->toBeNull();

    $sync->apply($provider, $user->username, ['groups' => []]);

    expect($manual->fresh()->isActive())->toBeTrue();
});

test('a role granted by a different provider is not revoked by this provider\'s sync', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $providerA = makeIdentityProvider($community->getShortCode(), 'Provider A');
    $providerB = makeIdentityProvider($community->getShortCode(), 'Provider B');
    $providerA->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    $sync = resolve(IdentityProviderGroupRoleSync::class);
    $sync->apply($providerA, $user->username, ['groups' => ['stura-member']]);

    // Provider B has no mapping for this committee/role at all, so its own
    // sync run must leave provider A's grant alone.
    $sync->apply($providerB, $user->username, ['groups' => []]);

    $membership = RoleMembership::where('username', $user->username)->first();
    expect($membership->isActive())->toBeTrue();
});

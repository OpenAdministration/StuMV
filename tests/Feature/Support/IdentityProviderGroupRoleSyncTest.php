<?php

use App\Models\RoleMembership;
use App\Support\SsoGroupRoleSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a mapped external group grants the corresponding role', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeSsoProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    resolve(SsoGroupRoleSync::class)->apply($provider, $user->username, ['groups' => ['stura-member']]);

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
    $provider = makeSsoProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    resolve(SsoGroupRoleSync::class)->apply($provider, $user->username, ['groups' => ['some-other-group']]);

    expect(RoleMembership::where('username', $user->username)->count())->toBe(0);
});

test('applying the same groups twice does not create a duplicate membership', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeSsoProvider($community->getShortCode());
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    $sync = resolve(SsoGroupRoleSync::class);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);
    $sync->apply($provider, $user->username, ['groups' => ['stura-member']]);

    expect(RoleMembership::where('username', $user->username)->count())->toBe(1);
});

test('a missing groups claim is a no-op', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $provider = makeSsoProvider($community->getShortCode());

    resolve(SsoGroupRoleSync::class)->apply($provider, $user->username, []);

    expect(RoleMembership::where('username', $user->username)->count())->toBe(0);
});

test('a custom groups_claim name is honoured', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);
    $committee = TestLdap::makeCommittee($community);
    $role = TestLdap::makeRole($committee);
    $provider = makeSsoProvider($community->getShortCode());
    $provider->update(['groups_claim' => 'memberOf']);
    $provider->roleMappings()->create([
        'external_group' => 'stura-member',
        'committee_dn' => $committee->getDn(),
        'role_cn' => $role->getFirstAttribute('cn'),
    ]);

    resolve(SsoGroupRoleSync::class)->apply($provider, $user->username, ['memberOf' => ['stura-member']]);

    expect(RoleMembership::where('username', $user->username)->count())->toBe(1);
});

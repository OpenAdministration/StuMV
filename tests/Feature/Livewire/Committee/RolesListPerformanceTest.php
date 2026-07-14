<?php

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Livewire\Committee\ListRoles;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Container;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * ListRoles::render() used to resolve each role's active members from LDAP
 * one at a time (User::findOrFailByUsername() per membership per role),
 * instead of batching every username across every role into a single
 * whereIn() query - count how many separate person-lookup query events fire
 * for role members (excluding the acting moderator's own user lookup, which
 * is unrelated - it's from the already-optimized moderator-ability check) to
 * catch a regression back to the per-member lookup.
 */
function countMemberLookupQueryEvents(Closure $callback, string $excludeUsername): int
{
    $queries = 0;
    Container::getInstance()->getDispatcher()->listen('LdapRecord\Query\Events\*', function ($eventName, $events) use (&$queries, $excludeUsername): void {
        foreach ($events as $event) {
            $query = $event->getQuery()->getUnescapedQuery();
            if (str_contains($query, 'objectclass=inetorgperson') && str_contains($query, 'uid=') && ! str_contains($query, "uid=$excludeUsername")) {
                $queries++;
            }
        }
    });

    $callback();

    return $queries;
}

function makeRoleWithActiveMembers(Committee $committee, string $cn, Community $community, int $memberCount): void
{
    $role = TestLdap::makeRole($committee, $cn);
    foreach (range(1, $memberCount) as $i) {
        RoleMembership::create([
            'role_cn' => $cn,
            'committee_dn' => $committee->getDn(),
            'username' => TestLdap::member($community)->username,
            'from' => today(),
        ]);
    }
}

test('resolving role members from LDAP is batched into a single query regardless of how many roles or members are shown', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    makeRoleWithActiveMembers($committee, 'role1', $community, 2);
    $moderator = actingAsModerator($community);

    $queriesForOneRole = countMemberLookupQueryEvents(function () use ($community, $committee): void {
        Livewire::test(ListRoles::class, ['uid' => $community, 'ou' => $committee->getFirstAttribute('ou')])
            ->call('loadRoles');
    }, $moderator->username);

    makeRoleWithActiveMembers($committee, 'role2', $community, 2);
    makeRoleWithActiveMembers($committee, 'role3', $community, 2);

    $queriesForThreeRoles = countMemberLookupQueryEvents(function () use ($community, $committee): void {
        Livewire::test(ListRoles::class, ['uid' => $community, 'ou' => $committee->getFirstAttribute('ou')])
            ->call('loadRoles');
    }, $moderator->username);

    expect($queriesForOneRole)->toBe(1)
        ->and($queriesForThreeRoles)->toBe(1);
});

test('roles still show their active members after batching the LDAP lookup', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);
    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today(),
    ]);
    actingAsModerator($community);

    $roleData = Livewire::test(ListRoles::class, ['uid' => $community, 'ou' => $committee->getFirstAttribute('ou')])
        ->call('loadRoles')
        ->viewData('roleData');

    $data = $roleData[$role->getDn()];
    expect($data['hasMembers'])->toBeTrue()
        ->and($data['members'])->toHaveCount(1)
        ->and($data['members'][0]->getFirstAttribute('uid'))->toBe($member->username);
});

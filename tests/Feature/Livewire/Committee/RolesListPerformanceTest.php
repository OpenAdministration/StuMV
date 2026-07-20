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
 * for role members (excluding entryuuid=-based lookups, which are
 * Auth::user()->ldap() resolving the acting user's own identity for
 * authorization checks, unrelated to resolving role members - a person is
 * never looked up that way here) to catch a regression back to the
 * per-member lookup.
 */
function countMemberLookupQueryEvents(Closure $callback): int
{
    $queries = 0;
    $listener = function ($eventName, $events) use (&$queries): void {
        foreach ($events as $event) {
            $query = $event->getQuery()->getUnescapedQuery();
            if (str_contains($query, 'objectclass=inetorgperson') && str_contains($query, '(uid=') && ! str_contains($query, 'entryuuid=')) {
                $queries++;
            }
        }
    };

    $dispatcher = Container::getInstance()->getDispatcher();
    $dispatcher->listen('LdapRecord\Query\Events\*', $listener);

    try {
        $callback();
    } finally {
        $dispatcher->forget('LdapRecord\Query\Events\*');
    }

    return $queries;
}

function makeRoleWithActiveMembers(Committee $committee, string $cn, Community $community, int $memberCount): void
{
    $role = TestLdap::makeRole($committee, $cn);
    foreach (range(1, $memberCount) as $i) {
        RoleMembership::create([
            'realm' => $community->getShortCode(),
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
    actingAsModerator($community);

    $queriesForOneRole = countMemberLookupQueryEvents(function () use ($community, $committee): void {
        Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => $committee->getFirstAttribute('ou')])
            ->call('loadRoles');
    });

    makeRoleWithActiveMembers($committee, 'role2', $community, 2);
    makeRoleWithActiveMembers($committee, 'role3', $community, 2);

    $queriesForThreeRoles = countMemberLookupQueryEvents(function () use ($community, $committee): void {
        Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => $committee->getFirstAttribute('ou')])
            ->call('loadRoles');
    });

    expect($queriesForOneRole)->toBe(1)
        ->and($queriesForThreeRoles)->toBe(1);
});

test('roles still show their active members after batching the LDAP lookup', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);
    RoleMembership::create([
        'realm' => $community->getShortCode(),
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today(),
    ]);
    actingAsModerator($community);

    $roleData = Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => $committee->getFirstAttribute('ou')])
        ->call('loadRoles')
        ->viewData('roleData');

    $data = $roleData[$role->getDn()];
    expect($data['hasMembers'])->toBeTrue()
        ->and($data['members'])->toHaveCount(1)
        ->and($data['members'][0]->getFirstAttribute('uid'))->toBe($member->username);
});

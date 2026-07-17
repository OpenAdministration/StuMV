<?php

use App\Ldap\Community;
use App\Ldap\User;
use App\Livewire\Committee\ListRoleMembers;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Container;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * edit/delete permissions (and the profile-link admin check) don't depend on
 * which row they're for - they're the same committee/community for every
 * membership shown - so they must be computed once per page, not once per
 * row (which would multiply an LDAP-hitting moderator ancestor-walk, i.e. a
 * "cn=moderators"/"cn=admins" group lookup, by the number of rows shown).
 * Count only those group lookups - unrelated per-row LDAP traffic (e.g.
 * hydrating each member's profile attributes) is out of scope here.
 */
function countModeratorAndAdminQueries(Closure $callback): int
{
    $queries = 0;
    Container::getInstance()->getDispatcher()->listen('LdapRecord\Query\Events\*', function ($eventName, $events) use (&$queries): void {
        foreach ($events as $event) {
            $query = $event->getQuery()->getUnescapedQuery();
            if (str_contains($query, 'cn=moderators') || str_contains($query, 'cn=admins')) {
                $queries++;
            }
        }
    });

    $callback();

    return $queries;
}

function countLdapQueries(Closure $callback): int
{
    $queries = 0;
    Container::getInstance()->getDispatcher()->listen('LdapRecord\Query\Events\*', function ($eventName, $events) use (&$queries): void {
        $queries += count($events);
    });

    $callback();

    return $queries;
}

test('cancelling the delete modal closes it', function (): void {
    $moderator = actingAsModerator('demo');

    $membership = RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => 'ou=FSR,ou=Committees,ou=demo,ou=Communities,dc=stumv,dc=de',
        'username' => $moderator->username,
        'from' => today(),
    ]);

    Livewire::test(ListRoleMembers::class, ['realm' => Community::findByUid('demo'), 'ou' => 'FSR', 'cn' => 'mitglied'])
        ->call('prepareDeletion', $membership->id)
        ->call('close')
        ->assertDispatched('modal-close', name: 'delete');
});

test('renders the member list for a seeded role', function (): void {
    actingAsModerator('demo');

    Livewire::test(ListRoleMembers::class, ['realm' => Community::findByUid('demo'), 'ou' => 'FSR', 'cn' => 'mitglied'])
        ->assertStatus(200)
        ->assertSet('cn', 'mitglied');
});

test('the member list can be lazily loaded', function (): void {
    actingAsModerator('demo');

    Livewire::test(ListRoleMembers::class, ['realm' => Community::findByUid('demo'), 'ou' => 'FSR', 'cn' => 'mitglied'])
        ->assertSet('ready', false)
        ->call('loadMembers')
        ->assertSet('ready', true);
});

test('the edit/delete permission check does not scale with the number of members shown', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    foreach (range(1, 2) as $i) {
        RoleMembership::create([
            'role_cn' => 'mitglied',
            'committee_dn' => $committee->getDn(),
            'username' => TestLdap::member($community)->username,
            'from' => today(),
        ]);
    }

    $queriesForTwo = countModeratorAndAdminQueries(function () use ($community, $committee, $role): void {
        Livewire::test(ListRoleMembers::class, [
            'realm' => $community,
            'ou' => $committee->getFirstAttribute('ou'),
            'cn' => $role->getFirstAttribute('cn'),
        ])->call('loadMembers');
    });

    foreach (range(1, 6) as $i) {
        RoleMembership::create([
            'role_cn' => 'mitglied',
            'committee_dn' => $committee->getDn(),
            'username' => TestLdap::member($community)->username,
            'from' => today(),
        ]);
    }

    $queriesForEight = countModeratorAndAdminQueries(function () use ($community, $committee, $role): void {
        Livewire::test(ListRoleMembers::class, [
            'realm' => $community,
            'ou' => $committee->getFirstAttribute('ou'),
            'cn' => $role->getFirstAttribute('cn'),
        ])->call('loadMembers');
    });

    expect($queriesForEight)->toBe($queriesForTwo);
});

test('a member already synced to the LDAP role group is not pending', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);
    TestLdap::attach($role, User::findByUsername($member->username));
    $membership = RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today(),
    ]);
    actingAsModerator($community);

    $status = Livewire::test(ListRoleMembers::class, [
        'realm' => $community,
        'ou' => $committee->getFirstAttribute('ou'),
        'cn' => $role->getFirstAttribute('cn'),
    ])
        ->call('loadMembers')
        ->viewData('memberStatuses')[$membership->id];

    expect($status)->toBe(['isActive' => true, 'isPending' => false]);
});

test('an active member not yet synced to the LDAP role group is pending', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);
    $membership = RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
        'from' => today(),
    ]);
    actingAsModerator($community);

    $status = Livewire::test(ListRoleMembers::class, [
        'realm' => $community,
        'ou' => $committee->getFirstAttribute('ou'),
        'cn' => $role->getFirstAttribute('cn'),
    ])
        ->call('loadMembers')
        ->viewData('memberStatuses')[$membership->id];

    expect($status)->toBe(['isActive' => true, 'isPending' => true]);
});

test('the total LDAP query count does not scale with the number of members shown', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    foreach (range(1, 2) as $i) {
        RoleMembership::create([
            'role_cn' => 'mitglied',
            'committee_dn' => $committee->getDn(),
            'username' => TestLdap::member($community)->username,
            'from' => today(),
        ]);
    }

    $queriesForTwo = countLdapQueries(function () use ($community, $committee, $role): void {
        Livewire::test(ListRoleMembers::class, [
            'realm' => $community,
            'ou' => $committee->getFirstAttribute('ou'),
            'cn' => $role->getFirstAttribute('cn'),
        ])->call('loadMembers');
    });

    foreach (range(1, 6) as $i) {
        RoleMembership::create([
            'role_cn' => 'mitglied',
            'committee_dn' => $committee->getDn(),
            'username' => TestLdap::member($community)->username,
            'from' => today(),
        ]);
    }

    $queriesForEight = countLdapQueries(function () use ($community, $committee, $role): void {
        Livewire::test(ListRoleMembers::class, [
            'realm' => $community,
            'ou' => $committee->getFirstAttribute('ou'),
            'cn' => $role->getFirstAttribute('cn'),
        ])->call('loadMembers');
    });

    expect($queriesForEight)->toBe($queriesForTwo);
});

test('the role members list is paginated to 10 per page', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    foreach (range(1, 15) as $i) {
        RoleMembership::create([
            'role_cn' => 'mitglied',
            'committee_dn' => $committee->getDn(),
            'username' => TestLdap::member($community)->username,
            'from' => today(),
        ]);
    }
    actingAsModerator($community);

    $component = Livewire::test(ListRoleMembers::class, ['realm' => $community, 'ou' => $committee->getFirstAttribute('ou'), 'cn' => $role->getFirstAttribute('cn')])
        ->call('loadMembers');

    expect($component->viewData('members'))->toHaveCount(10);

    $page2 = $component->call('gotoPage', 2)->viewData('members');
    expect($page2)->toHaveCount(5);
});

test('the current page is bound to the url before members are lazily loaded', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    $component = Livewire::test(ListRoleMembers::class, [
        'realm' => $community,
        'ou' => $committee->getFirstAttribute('ou'),
        'cn' => $role->getFirstAttribute('cn'),
    ]);

    expect($component->effects['url'] ?? [])->toHaveKey('paginators.page');
});

test('the search field filters role members by name', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $alice = TestLdap::member($community);
    User::findByUsername($alice->username)->fill(['cn' => 'Alice Wonder'])->save();
    $bob = TestLdap::member($community);
    User::findByUsername($bob->username)->fill(['cn' => 'Bob Builder'])->save();
    RoleMembership::create(['role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $alice->username, 'from' => today()]);
    RoleMembership::create(['role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $bob->username, 'from' => today()]);
    actingAsModerator($community);

    Livewire::test(ListRoleMembers::class, ['realm' => $community, 'ou' => $committee->getFirstAttribute('ou'), 'cn' => $role->getFirstAttribute('cn')])
        ->call('loadMembers')
        ->set('search', 'Alice')
        ->assertSee('Alice Wonder')
        ->assertDontSee('Bob Builder');
});

test('role members are sorted by name ascending by default', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    foreach (['Zebra', 'Apple', 'Mango'] as $name) {
        $member = TestLdap::member($community);
        User::findByUsername($member->username)->fill(['cn' => $name])->save();
        RoleMembership::create(['role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $member->username, 'from' => today()]);
    }
    actingAsModerator($community);

    $html = Livewire::test(ListRoleMembers::class, ['realm' => $community, 'ou' => $committee->getFirstAttribute('ou'), 'cn' => $role->getFirstAttribute('cn')])
        ->call('loadMembers')
        ->html();

    $posApple = strpos($html, 'Apple');
    $posMango = strpos($html, 'Mango');
    $posZebra = strpos($html, 'Zebra');

    expect($posApple)->toBeLessThan($posMango)
        ->and($posMango)->toBeLessThan($posZebra);
});

test('sortBy toggles direction and re-sorts role members descending', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    foreach (['Zebra', 'Apple', 'Mango'] as $name) {
        $member = TestLdap::member($community);
        User::findByUsername($member->username)->fill(['cn' => $name])->save();
        RoleMembership::create(['role_cn' => 'mitglied', 'committee_dn' => $committee->getDn(), 'username' => $member->username, 'from' => today()]);
    }
    actingAsModerator($community);

    $html = Livewire::test(ListRoleMembers::class, ['realm' => $community, 'ou' => $committee->getFirstAttribute('ou'), 'cn' => $role->getFirstAttribute('cn')])
        ->call('loadMembers')
        ->call('sortBy', 'name')
        ->assertSet('sortDirection', 'desc')
        ->html();

    $posApple = strpos($html, 'Apple');
    $posMango = strpos($html, 'Mango');
    $posZebra = strpos($html, 'Zebra');

    expect($posZebra)->toBeLessThan($posMango)
        ->and($posMango)->toBeLessThan($posApple);
});

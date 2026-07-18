<?php

use App\Livewire\Group\ListRolesInGroup;
use App\Models\GroupMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Container;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

function countLdapQueriesForRolesInGroup(Closure $callback): int
{
    $queries = 0;
    Container::getInstance()->getDispatcher()->listen('LdapRecord\Query\Events\*', function ($eventName, $events) use (&$queries): void {
        $queries += count($events);
    });

    $callback();

    return $queries;
}

test('shows a callout instead of an empty table when the group has no roles', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    $html = Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->call('loadRoles')
        ->assertSee(__('groups.no_roles_found'))
        ->html();

    expect($html)->not->toContain('<table');
});

test('shows a loading indicator before the roles finish loading', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->assertSet('ready', false)
        ->call('loadRoles')
        ->assertSet('ready', true);
});

test('deletePrepare shows the confirmation modal with the translated role name', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    $membership = GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);

    Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->call('loadRoles')
        ->call('deletePrepare', $membership->id)
        ->assertDispatched('modal-show', name: 'delete')
        ->assertDontSee('groups.delete_role_title')
        ->assertDontSee('groups.delete_role_warning')
        ->assertSee('Role mitglied');
});

test('deleting a role from a group removes only that specific row, even with duplicates', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    // Two duplicate relationship rows for the exact same group/role pair.
    $first = GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    $second = GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);

    Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->call('deletePrepare', $first->id)
        ->call('deleteCommit')
        ->assertDispatched('modal-close', name: 'delete');

    expect(GroupMembership::find($first->id))->toBeNull()
        ->and(GroupMembership::find($second->id))->not->toBeNull();
});

test('the roles-in-group list shows one row per membership row', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $role = TestLdap::makeRole($committee, 'mitglied');
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);

    $html = Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->call('loadRoles')
        ->html();

    expect(substr_count($html, 'Role mitglied'))->toBe(2);
});

test('the search filters the role list by role description', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $alpha = TestLdap::makeRole($committee);
    $alpha->fill(['description' => 'Alpha Role'])->save();
    $beta = TestLdap::makeRole($committee);
    $beta->fill(['description' => 'Beta Role'])->save();
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $alpha->getDn()]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $beta->getDn()]);

    Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->call('loadRoles')
        ->set('search', 'Alpha Role')
        ->assertSee('Alpha Role')
        ->assertDontSee('Beta Role');
});

test('the search filters the role list by committee description', function (): void {
    $community = newCommunity();
    $alphaCommittee = TestLdap::makeCommittee($community);
    $alphaCommittee->fill(['description' => 'Alpha Committee'])->save();
    $betaCommittee = TestLdap::makeCommittee($community);
    $betaCommittee->fill(['description' => 'Beta Committee'])->save();
    $alphaRole = TestLdap::makeRole($alphaCommittee);
    $betaRole = TestLdap::makeRole($betaCommittee);
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $alphaRole->getDn()]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $betaRole->getDn()]);

    Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->call('loadRoles')
        ->set('search', 'Alpha Committee')
        ->assertSee('Alpha Committee')
        ->assertDontSee('Beta Committee');
});

test('roles are sorted by committee description ascending by default', function (): void {
    $community = newCommunity();
    $zeta = TestLdap::makeCommittee($community);
    $zeta->fill(['description' => 'Zeta Committee'])->save();
    $alpha = TestLdap::makeCommittee($community);
    $alpha->fill(['description' => 'Alpha Committee'])->save();
    $zetaRole = TestLdap::makeRole($zeta);
    $alphaRole = TestLdap::makeRole($alpha);
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $zetaRole->getDn()]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $alphaRole->getDn()]);

    $html = Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->call('loadRoles')
        ->html();

    expect(strpos($html, 'Alpha Committee'))->toBeLessThan(strpos($html, 'Zeta Committee'));
});

test('sortBy toggles direction and re-sorts by committee description', function (): void {
    $community = newCommunity();
    $zeta = TestLdap::makeCommittee($community);
    $zeta->fill(['description' => 'Zeta Committee'])->save();
    $alpha = TestLdap::makeCommittee($community);
    $alpha->fill(['description' => 'Alpha Committee'])->save();
    $zetaRole = TestLdap::makeRole($zeta);
    $alphaRole = TestLdap::makeRole($alpha);
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $zetaRole->getDn()]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $alphaRole->getDn()]);

    $html = Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->call('loadRoles')
        ->call('sortBy', 'committee')
        ->assertSet('sortDirection', 'desc')
        ->html();

    expect(strpos($html, 'Zeta Committee'))->toBeLessThan(strpos($html, 'Alpha Committee'));
});

test('sortBy switches to sorting by role description when that column is clicked', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $zeta = TestLdap::makeRole($committee);
    $zeta->fill(['description' => 'Zeta Role'])->save();
    $alpha = TestLdap::makeRole($committee);
    $alpha->fill(['description' => 'Alpha Role'])->save();
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $zeta->getDn()]);
    GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $alpha->getDn()]);

    $html = Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->call('loadRoles')
        ->call('sortBy', 'role')
        ->assertSet('sortField', 'role')
        ->assertSet('sortDirection', 'asc')
        ->html();

    expect(strpos($html, 'Alpha Role'))->toBeLessThan(strpos($html, 'Zeta Role'));
});

test('the LDAP query count does not scale with the number of distinct roles/committees shown', function (): void {
    $community = newCommunity();
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    foreach (range(1, 2) as $i) {
        $committee = TestLdap::makeCommittee($community);
        $role = TestLdap::makeRole($committee);
        GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    }

    $queriesForTwo = countLdapQueriesForRolesInGroup(function () use ($community): void {
        Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
            ->call('loadRoles');
    });

    foreach (range(1, 6) as $i) {
        $committee = TestLdap::makeCommittee($community);
        $role = TestLdap::makeRole($committee);
        GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    }

    $queriesForEight = countLdapQueriesForRolesInGroup(function () use ($community): void {
        Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
            ->call('loadRoles');
    });

    expect($queriesForEight)->toBe($queriesForTwo);
});

test('the role list is paginated to 10 per page', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $group = TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    foreach (range(1, 11) as $i) {
        $role = TestLdap::makeRole($committee, sprintf('role%02d', $i));
        GroupMembership::create(['group_dn' => $group->getDn(), 'role_dn' => $role->getDn()]);
    }

    $component = Livewire::test(ListRolesInGroup::class, ['realm' => $community, 'cn' => 'newsletter'])
        ->call('loadRoles');

    expect(substr_count($component->html(), 'Role role'))->toBe(10);

    $component->call('gotoPage', 2);

    expect(substr_count($component->html(), 'Role role'))->toBe(1);
});

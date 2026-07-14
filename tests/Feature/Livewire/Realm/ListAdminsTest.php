<?php

use App\Livewire\Realm\ListAdmins;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Container;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * admin/remove_admin are the same check for every row (they only depend on
 * $community, never on the row) - count the LDAP admins-group lookups they
 * trigger to catch a regression back to per-row/per-check evaluation.
 */
function countAdminGroupQueries(Closure $callback): int
{
    $queries = 0;
    Container::getInstance()->getDispatcher()->listen('LdapRecord\Query\Events\*', function ($eventName, $events) use (&$queries): void {
        foreach ($events as $event) {
            $query = $event->getQuery()->getUnescapedQuery();
            if (str_contains($query, 'cn=admins')) {
                $queries++;
            }
        }
    });

    $callback();

    return $queries;
}

test('admins are sorted by name ascending by default', function (): void {
    $community = newCommunity();
    $adminsGroup = $community->adminsGroup();
    foreach (['zeta', 'alpha', 'mike'] as $uid) {
        TestLdap::attach($adminsGroup, TestLdap::makeUser($uid));
    }
    actingAsModerator($community);

    $cns = Livewire::test(ListAdmins::class, ['realm' => $community])
        ->call('loadAdmins')
        ->viewData('realm_admins')
        ->map(fn ($user) => $user->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($cns)->toBe(['Test alpha', 'Test mike', 'Test zeta']);
});

test('sortBy toggles direction and re-sorts the admins descending', function (): void {
    $community = newCommunity();
    $adminsGroup = $community->adminsGroup();
    foreach (['zeta', 'alpha', 'mike'] as $uid) {
        TestLdap::attach($adminsGroup, TestLdap::makeUser($uid));
    }
    actingAsModerator($community);

    $cns = Livewire::test(ListAdmins::class, ['realm' => $community])
        ->call('loadAdmins')
        ->call('sortBy', 'cn')
        ->assertSet('sortDirection', 'desc')
        ->viewData('realm_admins')
        ->map(fn ($user) => $user->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($cns)->toBe(['Test zeta', 'Test mike', 'Test alpha']);
});

test('the admins list is paginated to 10 per page', function (): void {
    $community = newCommunity();
    $adminsGroup = $community->adminsGroup();
    foreach (range(1, 15) as $i) {
        TestLdap::attach($adminsGroup, TestLdap::makeUser(sprintf('adm%02d', $i)));
    }
    actingAsModerator($community);

    $component = Livewire::test(ListAdmins::class, ['realm' => $community])->call('loadAdmins');

    expect($component->viewData('realm_admins'))->toHaveCount(10);

    $page2 = $component->call('gotoPage', 2)->viewData('realm_admins');
    expect($page2)->toHaveCount(5);
});

test('the search field filters the admins list', function (): void {
    $community = newCommunity();
    $adminsGroup = $community->adminsGroup();
    TestLdap::attach($adminsGroup, TestLdap::makeUser('alphaadmin'));
    TestLdap::attach($adminsGroup, TestLdap::makeUser('betaadmin'));
    actingAsModerator($community);

    Livewire::test(ListAdmins::class, ['realm' => $community])
        ->call('loadAdmins')
        ->set('search', 'alphaadmin')
        ->assertSee('Test alphaadmin')
        ->assertDontSee('Test betaadmin');
});

test('an admin sees a working profile link for other admins', function (): void {
    $community = newCommunity();
    $adminsGroup = $community->adminsGroup();
    TestLdap::attach($adminsGroup, TestLdap::makeUser('otheradmin'));
    actingAsAdmin($community);

    Livewire::test(ListAdmins::class, ['realm' => $community])
        ->call('loadAdmins')
        ->assertSeeHtml('href="'.route('profile', ['username' => 'otheradmin']).'"');
});

test('the admin permission check does not scale with the number of admins shown', function (): void {
    $community = newCommunity();
    $adminsGroup = $community->adminsGroup();
    actingAsAdmin($community);

    foreach (range(1, 2) as $i) {
        TestLdap::attach($adminsGroup, TestLdap::makeUser(sprintf('scaleadm%02d', $i)));
    }

    $queriesForTwo = countAdminGroupQueries(function () use ($community): void {
        Livewire::test(ListAdmins::class, ['realm' => $community])->call('loadAdmins');
    });

    foreach (range(3, 8) as $i) {
        TestLdap::attach($adminsGroup, TestLdap::makeUser(sprintf('scaleadm%02d', $i)));
    }

    $queriesForEight = countAdminGroupQueries(function () use ($community): void {
        Livewire::test(ListAdmins::class, ['realm' => $community])->call('loadAdmins');
    });

    expect($queriesForEight)->toBe($queriesForTwo);
});

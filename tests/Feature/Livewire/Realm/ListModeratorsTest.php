<?php

use App\Ldap\Community;
use App\Ldap\User;
use App\Livewire\Realm\ListModerators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Container;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * admin/remove_moderator are the same check for every row (they only depend
 * on $community, never on the row) - count the LDAP admins-group lookups
 * they trigger to catch a regression back to per-row/per-check evaluation.
 */
function countAdminGroupQueriesForModerators(Closure $callback): int
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

test('deletePrepare shows the confirmation modal', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $mod = TestLdap::moderator($community);

    Livewire::test(ListModerators::class, ['uid' => $community])
        ->call('loadModerators')
        ->call('deletePrepare', $mod->username)
        ->assertDispatched('modal-show', name: 'delete')
        ->assertSet('deleteMemberUsername', $mod->username)
        ->assertDontSee('realms.delete_mod_title')
        ->assertDontSee('realms.delete_mod_warning');
});

test('deleteCommit removes the moderator and closes the modal', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $mod = TestLdap::moderator($community);

    Livewire::test(ListModerators::class, ['uid' => $community])
        ->call('loadModerators')
        ->call('deletePrepare', $mod->username)
        ->call('deleteCommit')
        ->assertDispatched('modal-close', name: 'delete');

    $ldapCommunity = Community::findByUid($community->getShortCode());
    $ldapUser = User::findByUsername($mod->username);
    expect($ldapCommunity->moderatorsGroup()->members()->contains($ldapUser))->toBeFalse();
});

test('moderators are sorted by name ascending by default', function (): void {
    $community = newCommunity();
    $modsGroup = $community->moderatorsGroup();
    foreach (['zeta', 'alpha', 'mike'] as $uid) {
        TestLdap::attach($modsGroup, TestLdap::makeUser($uid));
    }
    actingAsAdmin($community);

    $cns = Livewire::test(ListModerators::class, ['uid' => $community])
        ->call('loadModerators')
        ->viewData('realm_members')
        ->map(fn ($user) => $user->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($cns)->toBe(['Test alpha', 'Test mike', 'Test zeta']);
});

test('sortBy toggles direction and re-sorts the moderators descending', function (): void {
    $community = newCommunity();
    $modsGroup = $community->moderatorsGroup();
    foreach (['zeta', 'alpha', 'mike'] as $uid) {
        TestLdap::attach($modsGroup, TestLdap::makeUser($uid));
    }
    actingAsAdmin($community);

    $cns = Livewire::test(ListModerators::class, ['uid' => $community])
        ->call('loadModerators')
        ->call('sortBy', 'cn')
        ->assertSet('sortDirection', 'desc')
        ->viewData('realm_members')
        ->map(fn ($user) => $user->getFirstAttribute('cn'))
        ->values()
        ->all();

    expect($cns)->toBe(['Test zeta', 'Test mike', 'Test alpha']);
});

test('the moderators list is paginated to 10 per page', function (): void {
    $community = newCommunity();
    $modsGroup = $community->moderatorsGroup();
    foreach (range(1, 15) as $i) {
        TestLdap::attach($modsGroup, TestLdap::makeUser(sprintf('mod%02d', $i)));
    }
    actingAsAdmin($community);

    $component = Livewire::test(ListModerators::class, ['uid' => $community])->call('loadModerators');

    expect($component->viewData('realm_members'))->toHaveCount(10);

    $page2 = $component->call('gotoPage', 2)->viewData('realm_members');
    expect($page2)->toHaveCount(5);
});

test('the search field filters the moderators list', function (): void {
    $community = newCommunity();
    $modsGroup = $community->moderatorsGroup();
    TestLdap::attach($modsGroup, TestLdap::makeUser('alphamod'));
    TestLdap::attach($modsGroup, TestLdap::makeUser('betamod'));
    actingAsAdmin($community);

    Livewire::test(ListModerators::class, ['uid' => $community])
        ->call('loadModerators')
        ->set('search', 'alphamod')
        ->assertSee('Test alphamod')
        ->assertDontSee('Test betamod');
});

test('an admin sees a working profile link for moderators', function (): void {
    $community = newCommunity();
    $modsGroup = $community->moderatorsGroup();
    TestLdap::attach($modsGroup, TestLdap::makeUser('othermod'));
    actingAsAdmin($community);

    Livewire::test(ListModerators::class, ['uid' => $community])
        ->call('loadModerators')
        ->assertSeeHtml('href="'.route('profile', ['username' => 'othermod']).'"');
});

test('the admin permission check does not scale with the number of moderators shown', function (): void {
    $community = newCommunity();
    $modsGroup = $community->moderatorsGroup();
    actingAsAdmin($community);

    foreach (range(1, 2) as $i) {
        TestLdap::attach($modsGroup, TestLdap::makeUser(sprintf('scalemod%02d', $i)));
    }

    $queriesForTwo = countAdminGroupQueriesForModerators(function () use ($community): void {
        Livewire::test(ListModerators::class, ['uid' => $community])->call('loadModerators');
    });

    foreach (range(3, 8) as $i) {
        TestLdap::attach($modsGroup, TestLdap::makeUser(sprintf('scalemod%02d', $i)));
    }

    $queriesForEight = countAdminGroupQueriesForModerators(function () use ($community): void {
        Livewire::test(ListModerators::class, ['uid' => $community])->call('loadModerators');
    });

    expect($queriesForEight)->toBe($queriesForTwo);
});

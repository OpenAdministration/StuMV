<?php

use App\Livewire\Realm\ListMembers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LdapRecord\Container;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/**
 * admin/moderator (and the profile link they gate) are the same check for
 * every row - they only depend on $community, never on the row's member -
 * so they must be computed once per page, not once per row and per menu item
 * (which would multiply an LDAP-hitting community admins/moderators group
 * lookup by several checks per row).
 */
function countAdminAndModeratorGroupQueries(Closure $callback): int
{
    $queries = 0;
    Container::getInstance()->getDispatcher()->listen('LdapRecord\Query\Events\*', function ($eventName, $events) use (&$queries): void {
        foreach ($events as $event) {
            $query = $event->getQuery()->getUnescapedQuery();
            if (str_contains($query, 'cn=admins') || str_contains($query, 'cn=moderators')) {
                $queries++;
            }
        }
    });

    $callback();

    return $queries;
}

/** Create a community member whose database record carries the realm + name. */
function realmMember(string $realmUid, string $fullName): string
{
    $member = TestLdap::member($realmUid);
    User::where('username', $member->username)->update([
        'realm' => $realmUid,
        'full_name' => $fullName,
    ]);

    return $member->username;
}

test('members are listed sorted by name', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    realmMember($uid, 'Zebra Person');
    realmMember($uid, 'Apple Person');
    realmMember($uid, 'Mango Person');
    actingAsMember($community);

    $html = Livewire::test(ListMembers::class, ['uid' => $community])
        ->call('loadMembers')
        ->html();

    $posApple = strpos($html, 'Apple Person');
    $posMango = strpos($html, 'Mango Person');
    $posZebra = strpos($html, 'Zebra Person');

    expect($posApple)->toBeLessThan($posMango)
        ->and($posMango)->toBeLessThan($posZebra);
});

test('sortBy toggles direction and re-sorts the member list descending', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    realmMember($uid, 'Zebra Person');
    realmMember($uid, 'Apple Person');
    realmMember($uid, 'Mango Person');
    actingAsMember($community);

    $html = Livewire::test(ListMembers::class, ['uid' => $community])
        ->call('loadMembers')
        ->call('sortBy', 'full_name')
        ->assertSet('sortDirection', 'desc')
        ->html();

    $posApple = strpos($html, 'Apple Person');
    $posMango = strpos($html, 'Mango Person');
    $posZebra = strpos($html, 'Zebra Person');

    expect($posZebra)->toBeLessThan($posMango)
        ->and($posMango)->toBeLessThan($posApple);
});

test('the member list filters by name', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    realmMember($uid, 'Alice Wonder');
    realmMember($uid, 'Bob Builder');
    actingAsMember($community);

    Livewire::test(ListMembers::class, ['uid' => $community])
        ->call('loadMembers')
        ->set('search', 'Alice')
        ->assertSee('Alice Wonder')
        ->assertDontSee('Bob Builder');
});

test('the browser tab title includes the community name', function (): void {
    app()->setLocale('en');
    $community = newCommunity();
    actingAsMember($community);

    $this->get(route('realms.members', ['uid' => $community->getShortCode()]))
        ->assertOk()
        ->assertSee('<title>Members of '.$community->getLongName().' | ', false);
});

test('search stays scoped to the community and does not leak other realms', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    // A member of THIS realm whose name does not match the search term.
    realmMember($uid, 'Alice Mine');

    // A user in a DIFFERENT realm whose username contains the search term.
    $leakUid = 'zzleak'.bin2hex(random_bytes(3));
    TestLdap::makeUser($leakUid);
    User::factory()->create([
        'username' => $leakUid,
        'realm' => 'some-other-realm',
        'full_name' => 'Leaker Person',
    ]);

    Livewire::test(ListMembers::class, ['uid' => $community])
        ->call('loadMembers')
        ->set('search', 'zzleak')
        ->assertDontSee('Leaker Person');
});

test('an admin sees a working profile link for members', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $username = realmMember($uid, 'Alice Wonder');
    actingAsAdmin($community);

    Livewire::test(ListMembers::class, ['uid' => $community])
        ->call('loadMembers')
        ->assertSeeHtml('href="'.route('profile', ['username' => $username]).'"');
});

test('a moderator sees the export-as-PDF control for members', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $username = realmMember($uid, 'Alice Wonder');
    actingAsModerator($community);

    Livewire::test(ListMembers::class, ['uid' => $community])
        ->call('loadMembers')
        ->assertSeeHtml('wire:click="exportPdf(\''.$username.'\')"');
});

test('the admin/moderator permission check does not scale with the number of members shown', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    foreach (range(1, 2) as $i) {
        realmMember($uid, "Member $i");
    }

    $queriesForTwo = countAdminAndModeratorGroupQueries(function () use ($community): void {
        Livewire::test(ListMembers::class, ['uid' => $community])->call('loadMembers');
    });

    foreach (range(3, 8) as $i) {
        realmMember($uid, "Member $i");
    }

    $queriesForEight = countAdminAndModeratorGroupQueries(function () use ($community): void {
        Livewire::test(ListMembers::class, ['uid' => $community])->call('loadMembers');
    });

    expect($queriesForEight)->toBe($queriesForTwo);
});

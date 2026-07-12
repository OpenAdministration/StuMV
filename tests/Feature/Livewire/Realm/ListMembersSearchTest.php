<?php

use App\Livewire\Realm\ListMembers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

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

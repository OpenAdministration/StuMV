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

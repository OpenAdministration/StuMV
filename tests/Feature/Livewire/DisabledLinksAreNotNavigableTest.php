<?php

use App\Livewire\Committee\ListRoles;
use App\Livewire\Realm\ListAdmins;
use App\Livewire\Realm\ListMembers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

/**
 * Flux's button-or-link-pure renders a plain <a href="..."> whenever an href
 * is given, and the disabled attribute has no native effect on anchors (only
 * on <button>) - a disabled-but-hrefed flux:button/flux:link/flux:menu.item
 * would still be fully clickable/navigable despite looking disabled. The fix
 * throughout the app is to make href conditionally null exactly when
 * disabled is true, which falls back to a real <button disabled> (verified
 * directly for committee-tree-node.blade.php in TreeModeratorPermissionsTest;
 * these cover the same pattern in a representative sample of the other
 * fixed views).
 */
uses(RefreshDatabase::class);

test('a plain member sees no href on the disabled Add Admin button', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    $this->actingAs($member);

    $html = Livewire::test(ListAdmins::class, ['uid' => $community])->html();

    $newAdminUrl = route('realms.admins.new', ['uid' => $community->getShortCode()]);
    expect($html)->not->toContain('href="'.$newAdminUrl.'"');
});

test('a plain member sees no href on another member\'s disabled profile link', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    $otherMember = TestLdap::member($community);
    \App\Models\User::where('username', $otherMember->username)->update(['realm' => $community->getShortCode()]);
    $this->actingAs($member);

    $html = Livewire::test(ListMembers::class, ['uid' => $community])
        ->call('loadMembers')
        ->html();

    $profileUrl = route('profile', ['username' => $otherMember->username]);
    expect($html)->not->toContain('href="'.$profileUrl.'"');
});

test('a plain member sees no href on the disabled New Role button', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $member = TestLdap::member($community);
    $this->actingAs($member);

    $html = Livewire::test(ListRoles::class, ['uid' => $community, 'ou' => 'fsr'])->html();

    $newRoleUrl = route('committees.roles.new', ['uid' => $community->getShortCode(), 'ou' => 'fsr']);
    expect($html)->not->toContain('href="'.$newRoleUrl.'"');
});

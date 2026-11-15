<?php

use App\Livewire\Committee\ListRoles;
use App\Livewire\Realm\ListAdmins;
use App\Livewire\Realm\ListMembers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

/** Actions the user has no permission for are hidden rather than shown disabled. */
uses(RefreshDatabase::class);

test('a plain member does not see the Add Admin button', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    $this->actingAs($member);

    $html = Livewire::test(ListAdmins::class, ['realm' => $community])->html();

    $newAdminUrl = route('realms.admins.new', ['realm' => $community->getShortCode()]);
    expect($html)->not->toContain('href="'.$newAdminUrl.'"')
        ->and($html)->not->toContain(__('realms.add_admin_button'));
});

test('a plain member does not see the edit link for another member', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    $otherMember = TestLdap::member($community);
    User::where('username', $otherMember->username)->update(['realm' => $community->getShortCode()]);
    $this->actingAs($member);

    $html = Livewire::test(ListMembers::class, ['realm' => $community])
        ->call('loadMembers')
        ->html();

    $profileUrl = route('profile', ['realm' => $community->getShortCode(), 'username' => $otherMember->username]);
    expect($html)->not->toContain('href="'.$profileUrl.'"');
});

test('a plain member does not see the New Role button', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    $member = TestLdap::member($community);
    $this->actingAs($member);

    $html = Livewire::test(ListRoles::class, ['realm' => $community, 'ou' => 'fsr'])->html();

    $newRoleUrl = route('committees.roles.new', ['realm' => $community->getShortCode(), 'ou' => 'fsr']);
    expect($html)->not->toContain('href="'.$newRoleUrl.'"')
        ->and($html)->not->toContain(__('roles.new_button'));
});

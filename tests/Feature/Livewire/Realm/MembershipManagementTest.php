<?php

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Livewire\Realm\ListAdmins;
use App\Livewire\Realm\NewAdmin;
use App\Livewire\Realm\NewMember;
use App\Livewire\Realm\NewModerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

/** True when the given group of the community lists this uid as a member. */
function groupHasUid(Community $community, string $group, string $uid): bool
{
    return $community->{$group}()->members()->get()
        ->contains(fn ($member) => $member->getFirstAttribute('uid') === $uid);
}

test('an admin can promote a member to admin', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    $memberDn = LdapUser::findByUsername($member->username)->getDn();
    actingAsAdmin($community);

    Livewire::test(NewAdmin::class, ['uid' => $community])
        ->set('dn', [$memberDn])
        ->call('save')
        ->assertHasNoErrors();

    expect(groupHasUid(Community::findByUid($community->getShortCode()), 'adminsGroup', $member->username))->toBeTrue();
});

test('an admin can remove another admin', function (): void {
    $community = newCommunity();
    $target = TestLdap::admin($community);
    actingAsAdmin($community);

    Livewire::test(ListAdmins::class, ['uid' => $community])
        ->call('deletePrepare', $target->username)
        ->call('deleteCommit');

    expect(groupHasUid(Community::findByUid($community->getShortCode()), 'adminsGroup', $target->username))->toBeFalse();
});

test('an admin can add a moderator', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    $memberDn = LdapUser::findByUsername($member->username)->getDn();
    actingAsAdmin($community);

    Livewire::test(NewModerator::class, ['uid' => $community])
        ->set('dn', [$memberDn])
        ->call('save')
        ->assertHasNoErrors();

    expect(groupHasUid(Community::findByUid($community->getShortCode()), 'moderatorsGroup', $member->username))->toBeTrue();
});

test('a super admin can add a community member', function (): void {
    $community = newCommunity();
    $outsider = TestLdap::makeUser();
    actingAsSuperAdmin();

    Livewire::test(NewMember::class, ['uid' => $community])
        ->set('selectedUsers', [$outsider->getDn()])
        ->call('save')
        ->assertHasNoErrors();

    expect(groupHasUid(Community::findByUid($community->getShortCode()), 'membersGroup', $outsider->getFirstAttribute('uid')))->toBeTrue();
});

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

    Livewire::test(NewAdmin::class, ['realm' => $community])
        ->set('dn', [$memberDn])
        ->call('save')
        ->assertHasNoErrors();

    expect(groupHasUid(Community::findByUid($community->getShortCode()), 'adminsGroup', $member->username))->toBeTrue();
});

test('an admin can remove another admin', function (): void {
    $community = newCommunity();
    $target = TestLdap::admin($community);
    actingAsAdmin($community);

    Livewire::test(ListAdmins::class, ['realm' => $community])
        ->call('deletePrepare', $target->username)
        ->call('deleteCommit');

    expect(groupHasUid(Community::findByUid($community->getShortCode()), 'adminsGroup', $target->username))->toBeFalse();
});

test('an admin can add a moderator', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    $memberDn = LdapUser::findByUsername($member->username)->getDn();
    actingAsAdmin($community);

    Livewire::test(NewModerator::class, ['realm' => $community])
        ->set('dn', [$memberDn])
        ->call('save')
        ->assertHasNoErrors();

    expect(groupHasUid(Community::findByUid($community->getShortCode()), 'moderatorsGroup', $member->username))->toBeTrue();
});

test('a super admin can add a community member', function (): void {
    // NewMember is a registration form (creates a brand-new account directly
    // under the realm's own People branch), not a picker over existing
    // accounts - membership is the location itself.
    $community = newCommunity();
    $username = 'newmem'.bin2hex(random_bytes(4));
    $password = 'Aa1!'.bin2hex(random_bytes(8));
    actingAsSuperAdmin();

    Livewire::test(NewMember::class, ['realm' => $community])
        ->set('email', $username.'@example.test')
        ->set('first_name', 'New')
        ->set('last_name', 'Member')
        ->set('username', $username)
        ->set('password', $password)
        ->set('password_confirmation', $password)
        ->call('save')
        ->assertHasNoErrors();

    $ldapUser = LdapUser::findByUsername($username);
    TestLdap::track($ldapUser);

    expect($ldapUser)->not->toBeNull()
        ->and($ldapUser->getDn())->toEndWith(','.$community->peopleDn())
        ->and(\App\Models\User::where('username', $username)->value('realm'))->toBe($community->getShortCode());
});

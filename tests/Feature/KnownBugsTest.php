<?php

use App\Ldap\User as LdapUser;
use App\Livewire\Committee\AddUserToRole;
use App\Rules\UserIsMember;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\Support\TestLdap;

/**
 * These tests assert the CORRECT behaviour of code that currently has bugs, so
 * they are RED on purpose. They document confirmed defects for review; once the
 * fixes land they turn green and become regression guards. Do not "fix" them by
 * weakening the assertion — fix the production code.
 */
uses(RefreshDatabase::class);

test('BUG: UserIsMember lets a non-existent username pass validation', function (): void {
    $community = newCommunity();

    // A username that exists in neither LDAP nor the community.
    $passes = Validator::make(
        ['user' => 'ghost-'.uniqid()],
        ['user' => [new UserIsMember($community->getShortCode())]]
    )->passes();

    // UserIsMember only calls $fail when the user EXISTS but isn't a member
    // (guarded by `! empty($value)`), so an unknown username slips through.
    expect($passes)->toBeFalse();
});

test('BUG: adding a non-existent user to a role crashes instead of erroring', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    // Because UserIsMember passes the unknown username, AddUserToRole proceeds
    // to insert a RoleMembership and hits the username foreign key -> 500,
    // rather than surfacing a validation error to the user.
    Livewire::test(AddUserToRole::class, ['uid' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->set('usernames', ['ghost-'.uniqid()])
        ->set('start_date', today()->format('Y-m-d'))
        ->call('save')
        ->assertHasErrors('usernames.0');
});

test('BUG: App\Ldap\User::adminOf returns the admins groups, not members', function (): void {
    $community = newCommunity();
    $admin = actingAsAdmin($community);
    $ldap = LdapUser::findByUsername($admin->username);

    $cns = $ldap->adminOf()->get()->map(fn ($group) => $group->getFirstAttribute('cn'));

    // adminOf() filters cn='members' (copy-paste), so it returns the members
    // group instead of the admins group the method name promises.
    expect($cns)->toContain('admins');
});

test('BUG: App\Ldap\User::moderatorOf returns the moderators groups, not members', function (): void {
    $community = newCommunity();
    $moderator = actingAsModerator($community);
    $ldap = LdapUser::findByUsername($moderator->username);

    $cns = $ldap->moderatorOf()->get()->map(fn ($group) => $group->getFirstAttribute('cn'));

    // moderatorOf() also filters cn='members'.
    expect($cns)->toContain('moderators');
});

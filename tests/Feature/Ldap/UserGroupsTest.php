<?php

use App\Ldap\User as LdapUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * App\Ldap\User exposes memberOf()/moderatorOf()/adminOf() relations that list
 * the community groups a user belongs to at each level. These pin that each one
 * targets the group its name promises (a regression guard: adminOf/moderatorOf
 * previously copy-pasted memberOf and all filtered cn='members').
 */
uses(RefreshDatabase::class);

test('memberOf lists the members groups the user belongs to', function (): void {
    $community = newCommunity();
    $member = actingAsMember($community);

    $cns = LdapUser::findByUsername($member->username)->memberOf()->get()
        ->map(fn ($group) => $group->getFirstAttribute('cn'));

    expect($cns)->toContain('members')->not->toContain('admins');
});

test('adminOf lists the admins groups the user belongs to', function (): void {
    $community = newCommunity();
    $admin = actingAsAdmin($community);

    $cns = LdapUser::findByUsername($admin->username)->adminOf()->get()
        ->map(fn ($group) => $group->getFirstAttribute('cn'));

    expect($cns)->toContain('admins');
});

test('moderatorOf lists the moderators groups the user belongs to', function (): void {
    $community = newCommunity();
    $moderator = actingAsModerator($community);

    $cns = LdapUser::findByUsername($moderator->username)->moderatorOf()->get()
        ->map(fn ($group) => $group->getFirstAttribute('cn'));

    expect($cns)->toContain('moderators');
});

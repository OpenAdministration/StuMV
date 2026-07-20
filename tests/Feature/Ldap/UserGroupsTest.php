<?php

use App\Ldap\User as LdapUser;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * App\Ldap\User exposes moderatorOf()/adminOf() relations that list the
 * community groups a user belongs to at each level. These pin that each one
 * targets the group its name promises (a regression guard: adminOf/moderatorOf
 * previously copy-pasted each other's cn filter).
 */
uses(RefreshDatabase::class);

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

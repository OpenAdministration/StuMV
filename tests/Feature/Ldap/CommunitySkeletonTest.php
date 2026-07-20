<?php

use App\Ldap\Committee;
use App\Ldap\Community;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use Tests\Support\TestLdap;

/**
 * Exercises the on-the-fly directory builder (Tests\Support\TestLdap) and, with
 * it, App\Ldap\Community::generateSkeleton() — the code that lays out a new
 * community's People/Groups/Committees/Domains OUs and its admins/moderators
 * groups (membership is the location itself now, there is no members group).
 * Everything created here is torn down by the global afterEach.
 */
test('a freshly built community has the full group skeleton', function (): void {
    $community = newCommunity();

    $found = Community::findByUid($community->getShortCode());

    expect($found)->not->toBeNull()
        ->and(OrganizationalUnit::query()->find($found->peopleDn()))->not->toBeNull()
        ->and($found->moderatorsGroup())->not->toBeNull()
        ->and($found->adminsGroup())->not->toBeNull();
});

test('committees, roles and groups can be attached to a built community', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();

    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    TestLdap::makeGroup($community, 'newsletter');

    expect(Committee::findByName($uid, 'fsr'))->not->toBeNull()
        ->and($committee->roles()->where('cn', 'mitglied')->exists())->toBeTrue();
});

test('nested committees resolve their parent', function (): void {
    $community = newCommunity();

    $parent = TestLdap::makeCommittee($community, 'stura');
    $child = TestLdap::makeCommittee($community, 'finanzen', parentDn: $parent->getDn());

    expect($child->parentCommittee())->not->toBeNull()
        ->and($child->parentCommittee()->getShortName())->toBe('stura');
});

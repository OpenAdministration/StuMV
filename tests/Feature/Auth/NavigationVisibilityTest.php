<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The dashboard body also links to the admins/groups pages via its own cards,
 * gated the same way as the sidebar item, so both should appear or disappear
 * together for a given role.
 */
function linkCount(string $html, string $uid, string $segment): int
{
    return substr_count($html, '/'.$uid.'/'.$segment.'"');
}

function adminLinkCount(string $html, string $uid): int
{
    return linkCount($html, $uid, 'admins');
}

function groupsLinkCount(string $html, string $uid): int
{
    return linkCount($html, $uid, 'groups');
}

function domainsLinkCount(string $html, string $uid): int
{
    return linkCount($html, $uid, 'domains');
}

test('a plain member does not see the admin nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(adminLinkCount($html, $uid))->toBe(0);
});

test('a moderator sees the admin nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsModerator($community);

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(adminLinkCount($html, $uid))->toBe(2);
});

test('an admin sees the admin nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(adminLinkCount($html, $uid))->toBe(2);
});

test('a super admin sees the admin nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsSuperAdmin();

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(adminLinkCount($html, $uid))->toBe(2);
});

test('a plain member does not see the groups nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(groupsLinkCount($html, $uid))->toBe(0);
});

test('a moderator does not see the groups nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsModerator($community);

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(groupsLinkCount($html, $uid))->toBe(0);
});

test('an admin sees the groups nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(groupsLinkCount($html, $uid))->toBe(2);
});

test('a super admin sees the groups nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsSuperAdmin();

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(groupsLinkCount($html, $uid))->toBe(2);
});

test('a plain member does not see the domains nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(domainsLinkCount($html, $uid))->toBe(0);
});

test('a moderator does not see the domains nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsModerator($community);

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(domainsLinkCount($html, $uid))->toBe(0);
});

test('an admin sees the domains nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(domainsLinkCount($html, $uid))->toBe(2);
});

test('a super admin sees the domains nav link', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsSuperAdmin();

    $html = $this->get(route('realms.dashboard', ['realm' => $uid]))
        ->assertOk()
        ->getContent();

    expect(domainsLinkCount($html, $uid))->toBe(2);
});

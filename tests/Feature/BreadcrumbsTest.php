<?php

use Tests\Support\TestLdap;

test('the role-members breadcrumb shows the role description, not its short code', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    $response = $this->get(route('committees.roles.members', [
        'realm' => $uid,
        'ou' => 'fsr',
        'cn' => 'mitglied',
    ]));

    // Scope the assertion to the breadcrumbs bar only (bounded by the next
    // header element): the page heading also shows the role description, so
    // an unbounded/whole-page assertSee() would pass even without the fix.
    preg_match('#data-flux-breadcrumbs>(.*?)ml-auto flex justify-end#s', (string) $response->getContent(), $section);

    expect($section[1] ?? '')
        ->toContain('Role mitglied')
        ->not->toContain('>mitglied<');
});

test('the breadcrumbs bar starts with a home icon linking to /', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $response = $this->get(route('realms.members', ['realm' => $uid]));

    preg_match('#data-flux-breadcrumbs>(.*?)ml-auto flex justify-end#s', (string) $response->getContent(), $section);
    $html = $section[1] ?? '';

    expect($html)->toContain('href="/"')
        ->and(strpos($html, 'href="/"'))->toBeLessThan(strpos($html, (string) $community->getLongName()));
});

test('the group members page has a breadcrumb showing the group and linking back to the groups list', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    TestLdap::makeGroup($community, 'newsletter');
    actingAsAdmin($community);

    $response = $this->get(route('realms.groups.members', ['realm' => $uid, 'cn' => 'newsletter']));

    $response->assertOk();

    preg_match('#data-flux-breadcrumbs>(.*?)ml-auto flex justify-end#s', (string) $response->getContent(), $section);

    expect($section[1] ?? '')
        ->toContain('newsletter')
        ->toContain(route('realms.groups', ['realm' => $uid]));
});

test('the community name is not truncated when it is the only breadcrumb item, as on the dashboard', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $response = $this->get(route('realms.dashboard', ['realm' => $uid]));

    $response->assertOk();

    preg_match('#data-flux-breadcrumbs>(.*?)ml-auto flex justify-end#s', (string) $response->getContent(), $section);
    $html = $section[1] ?? '';

    expect($html)->toContain((string) $community->getLongName())
        ->not->toContain('max-w-[20ch]');
});

test('breadcrumb titles that are not committee names are also width-capped and truncated', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $response = $this->get(route('realms.members', ['realm' => $uid]));

    preg_match('#data-flux-breadcrumbs>(.*?)ml-auto flex justify-end#s', (string) $response->getContent(), $section);

    expect($section[1] ?? '')->toContain('max-w-[20ch]');
});

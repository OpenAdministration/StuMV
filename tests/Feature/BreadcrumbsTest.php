<?php

use Tests\Support\TestLdap;

test('the role-members breadcrumb shows the role description, not its short code', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    $response = $this->get(route('committees.roles.members', [
        'uid' => $uid,
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

test('the breadcrumbs bar starts with a home icon linking to the realm dashboard', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $response = $this->get(route('realms.members', ['uid' => $uid]));

    preg_match('#data-flux-breadcrumbs>(.*?)ml-auto flex justify-end#s', (string) $response->getContent(), $section);
    $html = $section[1] ?? '';

    $homeUrl = route('realms.dashboard', $uid);

    // The community-name breadcrumb already links to this same dashboard
    // URL, so the icon item existing means the href now appears twice (icon
    // + community name) - once means only the pre-existing link is there.
    expect(substr_count($html, 'href="'.$homeUrl.'"'))->toBe(2);

    // And the first breadcrumb item (before the community's own name) must
    // be icon-only, not a second copy of the name.
    $firstItemEnd = strpos($html, 'data-flux-breadcrumbs-item', strpos($html, 'data-flux-breadcrumbs-item') + 1);
    $firstItem = substr($html, 0, $firstItemEnd);
    expect($firstItem)->not->toContain($community->getLongName());
});

test('breadcrumb titles that are not committee names are also width-capped and truncated', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $response = $this->get(route('realms.members', ['uid' => $uid]));

    preg_match('#data-flux-breadcrumbs>(.*?)ml-auto flex justify-end#s', (string) $response->getContent(), $section);

    expect($section[1] ?? '')->toContain('max-w-[20ch]');
});

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

test('the breadcrumbs bar starts with a home icon linking to /', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $response = $this->get(route('realms.members', ['uid' => $uid]));

    preg_match('#data-flux-breadcrumbs>(.*?)ml-auto flex justify-end#s', (string) $response->getContent(), $section);
    $html = $section[1] ?? '';

    expect($html)->toContain('href="/"')
        ->and(strpos($html, 'href="/"'))->toBeLessThan(strpos($html, $community->getLongName()));
});

test('breadcrumb titles that are not committee names are also width-capped and truncated', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsMember($community);

    $response = $this->get(route('realms.members', ['uid' => $uid]));

    preg_match('#data-flux-breadcrumbs>(.*?)ml-auto flex justify-end#s', (string) $response->getContent(), $section);

    expect($section[1] ?? '')->toContain('max-w-[20ch]');
});

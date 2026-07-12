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
    preg_match('#data-flux-breadcrumbs>(.*?)ml-auto flex justify-end#s', $response->getContent(), $section);

    expect($section[1] ?? '')
        ->toContain('Role mitglied')
        ->not->toContain('>mitglied<');
});

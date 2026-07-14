<?php

use App\Ldap\User;
use App\Livewire\Tools\CompareEmailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('matches are rendered as cards', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    actingAsModerator($community);

    $ldapUser = User::findByUsername($member->username);

    $response = Livewire::test(CompareEmailList::class, ['uid' => $community])
        ->set('emailAddressesInput', $ldapUser->getFirstAttribute('mail'))
        ->call('compareEmailAddressesWithLdap')
        ->assertSeeHtml('data-flux-card')
        ->assertSee($ldapUser->getFirstAttribute('cn'));

    // The typed email is expected to still appear inside the input textarea -
    // scope the "no email addresses in the results" assertion to the matches
    // fieldset only, not the whole page.
    preg_match('#data-flux-fieldset.*#s', $response->html(), $section);

    expect($section[0] ?? '')->not->toContain($ldapUser->getFirstAttribute('mail'));
});

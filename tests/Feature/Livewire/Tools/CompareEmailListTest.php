<?php

use App\Livewire\Tools\CompareEmailList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('matches are rendered as cards', function (): void {
    $community = newCommunity();
    $member = TestLdap::member($community);
    actingAsModerator($community);

    $ldapUser = \App\Ldap\User::findByUsername($member->username);

    Livewire::test(CompareEmailList::class, ['uid' => $community])
        ->set('emailAddressesInput', $ldapUser->getFirstAttribute('mail'))
        ->call('compareEmailAddressesWithLdap')
        ->assertSeeHtml('data-flux-card')
        ->assertSee($ldapUser->getFirstAttribute('cn'))
        ->assertSee($ldapUser->getFirstAttribute('mail'));
});

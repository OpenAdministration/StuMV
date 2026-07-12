<?php

use App\Livewire\Group\ListGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('groups are listed without using the LDAP slice/VLV query', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'grp1');
    TestLdap::makeGroup($community, 'grp2');
    actingAsModerator($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->assertSee('grp1')
        ->assertSee('grp2');
});

test('the group search filters the list', function (): void {
    $community = newCommunity();
    TestLdap::makeGroup($community, 'alpha');
    TestLdap::makeGroup($community, 'beta');
    actingAsModerator($community);

    Livewire::test(ListGroups::class, ['uid' => $community])
        ->set('search', 'alpha')
        ->assertSee('alpha')
        ->assertDontSee('beta');
});

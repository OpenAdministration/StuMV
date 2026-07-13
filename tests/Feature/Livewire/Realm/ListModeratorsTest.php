<?php

use App\Ldap\Community;
use App\Ldap\User;
use App\Livewire\Realm\ListModerators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('deletePrepare shows the confirmation modal', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $mod = TestLdap::moderator($community);

    Livewire::test(ListModerators::class, ['uid' => $community])
        ->call('loadModerators')
        ->call('deletePrepare', $mod->username)
        ->assertDispatched('modal-show', name: 'delete')
        ->assertSet('deleteMemberUsername', $mod->username)
        ->assertDontSee('realms.delete_mod_title')
        ->assertDontSee('realms.delete_mod_warning');
});

test('deleteCommit removes the moderator and closes the modal', function (): void {
    $community = newCommunity();
    actingAsAdmin($community);
    $mod = TestLdap::moderator($community);

    Livewire::test(ListModerators::class, ['uid' => $community])
        ->call('loadModerators')
        ->call('deletePrepare', $mod->username)
        ->call('deleteCommit')
        ->assertDispatched('modal-close', name: 'delete');

    $ldapCommunity = Community::findByUid($community->getShortCode());
    $ldapUser = User::findByUsername($mod->username);
    expect($ldapCommunity->moderatorsGroup()->members()->contains($ldapUser))->toBeFalse();
});

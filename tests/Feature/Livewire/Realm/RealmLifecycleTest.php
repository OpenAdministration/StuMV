<?php

use App\Ldap\Community;
use App\Ldap\Domain;
use App\Livewire\Realm\EditRealm;
use App\Livewire\Realm\ListRealms;
use App\Livewire\Realm\NewDomain;
use App\Livewire\Realm\NewRealm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a super admin can create a realm with the full skeleton', function (): void {
    actingAsSuperAdmin();
    $uid = 'trealm'.bin2hex(random_bytes(3));

    Livewire::test(NewRealm::class)
        ->set('uid', $uid)
        ->set('name', 'Test Realm '.$uid)
        ->call('save')
        ->assertHasNoErrors();

    $community = Community::findByUid($uid);
    TestLdap::track($community); // hand the component-created community to teardown

    expect($community)->not->toBeNull()
        ->and(\LdapRecord\Models\OpenLDAP\OrganizationalUnit::query()->find($community->peopleDn()))->not->toBeNull()
        ->and($community->adminsGroup())->not->toBeNull();
});

test('an admin can rename their realm', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);

    Livewire::test(EditRealm::class, ['realm' => $community])
        ->set('name', 'Renamed Realm')
        ->call('save')
        ->assertHasNoErrors();

    expect(Community::findByUid($uid)->getFirstAttribute('description'))->toBe('Renamed Realm');
});

test('a super admin can delete a realm once the shortcode is confirmed', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)
        ->call('deletePrepare', $uid)
        ->set('deleteConfirmText', $uid)
        ->call('deleteCommit');

    expect(Community::findByUid($uid))->toBeNull();
});

test('deleting a realm is rejected if the typed shortcode does not match', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsSuperAdmin();

    Livewire::test(ListRealms::class)
        ->call('deletePrepare', $uid)
        ->set('deleteConfirmText', 'wrong-code')
        ->call('deleteCommit')
        ->assertHasErrors('deleteConfirmText');

    expect(Community::findByUid($uid))->not->toBeNull();
});

test('an admin can add a domain to their realm', function (): void {
    $community = newCommunity();
    $uid = $community->getShortCode();
    actingAsAdmin($community);
    $dc = 'dom'.bin2hex(random_bytes(3)).'.de';

    Livewire::test(NewDomain::class, ['realm' => $community])
        ->set('dc', $dc)
        ->call('save')
        ->assertHasNoErrors();

    expect(Domain::findBy('dc', $dc))->not->toBeNull();
});

<?php

use App\Ldap\Community;
use App\Livewire\Realm\NewRealm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    Community::findByUid('reprotest')?->delete(recursive: true);
});

test('a superadmin can create a realm with a non-ascii name', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewRealm::class)
        ->set('uid', 'reprotest')
        ->set('name', 'Fachschaftsrat Ökonomie')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('realms.pick'));

    expect(Community::findByUid('reprotest')?->getLongName())->toBe('Fachschaftsrat Ökonomie');
});

test('creating a realm with a shortcode that already exists shows an error', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewRealm::class)
        ->set('uid', 'demo')
        ->set('name', 'Duplicate Demo Realm')
        ->call('save')
        ->assertHasErrors('uid')
        ->assertNoRedirect();
});

test('an invalid name is rejected before hitting LDAP', function (): void {
    actingAsSuperAdmin();

    Livewire::test(NewRealm::class)
        ->set('uid', 'reprotest')
        ->set('name', 'a')
        ->call('save')
        ->assertHasErrors('name')
        ->assertNoRedirect();

    expect(Community::findByUid('reprotest'))->toBeNull();
});

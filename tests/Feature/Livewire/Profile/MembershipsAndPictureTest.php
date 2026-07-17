<?php

use App\Livewire\Profile\Memberships;
use App\Livewire\Profile\Picture;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the memberships page lists a users active role memberships', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $user = actingAsMember($community);

    RoleMembership::create([
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $user->username,
        'from' => today()->subMonth(),
    ]);

    Livewire::test(Memberships::class, ['username' => $user->username])
        ->assertStatus(200)
        ->assertSee('Role mitglied'); // the role description set by the factory
});

test('the memberships page shows a callout when there are no memberships', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Memberships::class, ['username' => $user->username])
        ->assertSee(__('profile.no_memberships_found'));
});

test('a user cannot view someone else memberships', function (): void {
    $community = newCommunity();
    actingAsMember($community);
    $someoneElse = TestLdap::makeUser();

    Livewire::test(Memberships::class, ['username' => $someoneElse->getFirstAttribute('uid')])
        ->assertForbidden();
});

test('the profile picture page renders for the owner', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Picture::class, ['username' => $user->username])
        ->assertStatus(200);
});

test('a user cannot open someone else picture page', function (): void {
    $community = newCommunity();
    actingAsMember($community);
    $someoneElse = TestLdap::makeUser();

    Livewire::test(Picture::class, ['username' => $someoneElse->getFirstAttribute('uid')])
        ->assertForbidden();
});

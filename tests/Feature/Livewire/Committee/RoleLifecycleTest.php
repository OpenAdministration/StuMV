<?php

use App\Livewire\Committee\AddUserToRole;
use App\Livewire\Committee\EditRole;
use App\Livewire\Committee\NewRole;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('a moderator can create a role in a committee', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    actingAsModerator($community);

    Livewire::test(NewRole::class, ['uid' => $community, 'ou' => 'fsr'])
        ->set('cn', 'kasse')
        ->set('description', 'Kassenwart')
        ->call('save')
        ->assertHasNoErrors();

    expect($committee->roles()->where('cn', 'kasse')->exists())->toBeTrue();
});

test('moderators is reserved as a role name since it names the committee\'s hidden moderators group', function (): void {
    $community = newCommunity();
    TestLdap::makeCommittee($community, 'fsr');
    actingAsModerator($community);

    Livewire::test(NewRole::class, ['uid' => $community, 'ou' => 'fsr'])
        ->set('cn', 'moderators')
        ->set('description', 'Sneaky Role')
        ->call('save')
        ->assertHasErrors('cn');
});

test('a moderator can rename a role', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    Livewire::test(EditRole::class, ['uid' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->set('description', 'Ordentliches Mitglied')
        ->call('save')
        ->assertHasNoErrors();

    $role = $committee->roles()->where('cn', 'mitglied')->first();
    expect($role->getFirstAttribute('description'))->toBe('Ordentliches Mitglied');
});

test('a moderator can add a community member to a role', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $member = TestLdap::member($community);
    actingAsModerator($community);

    Livewire::test(AddUserToRole::class, ['uid' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->set('usernames', [$member->username])
        ->set('start_date', today()->format('Y-m-d'))
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('role_user_relation', [
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $member->username,
    ]);
});

test('a user who is not a member of the community cannot be added to a role', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    // An LDAP user that exists but is not a member of this community.
    $outsider = TestLdap::makeUser();
    actingAsModerator($community);

    Livewire::test(AddUserToRole::class, ['uid' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->set('usernames', [$outsider->getFirstAttribute('uid')])
        ->set('start_date', today()->format('Y-m-d'))
        ->call('save')
        ->assertHasErrors('usernames.0');

    expect(RoleMembership::count())->toBe(0);
});

test('an unknown username is rejected with a validation error, not a crash', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    actingAsModerator($community);

    // Regression: previously UserIsMember let unknown usernames through and the
    // RoleMembership insert then hit the username foreign key (500).
    Livewire::test(AddUserToRole::class, ['uid' => $community, 'ou' => 'fsr', 'cn' => 'mitglied'])
        ->set('usernames', ['ghost-'.uniqid()])
        ->set('start_date', today()->format('Y-m-d'))
        ->call('save')
        ->assertHasErrors('usernames.0');

    expect(RoleMembership::count())->toBe(0);
});

<?php

use App\Livewire\Profile\Memberships;
use App\Livewire\Profile\Picture;
use App\Models\ProfilePicture;
use App\Models\RoleMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Support\TestLdap;

uses(RefreshDatabase::class);

test('the memberships page lists a users active role memberships', function (): void {
    $community = newCommunity();
    $committee = TestLdap::makeCommittee($community, 'fsr');
    TestLdap::makeRole($committee, 'mitglied');
    $user = actingAsMember($community);

    RoleMembership::create([
        'realm' => $community->getShortCode(),
        'role_cn' => 'mitglied',
        'committee_dn' => $committee->getDn(),
        'username' => $user->username,
        'from' => today()->subMonth(),
    ]);

    Livewire::test(Memberships::class, ['realm' => $community, 'username' => $user->username])
        ->assertStatus(200)
        ->assertSee('Role mitglied'); // the role description set by the factory
});

test('the memberships page shows a callout when there are no memberships', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Memberships::class, ['realm' => $community, 'username' => $user->username])
        ->assertSee(__('profile.no_memberships_found'));
});

test('a user cannot view someone else memberships', function (): void {
    $community = newCommunity();
    actingAsMember($community);
    $someoneElse = TestLdap::makeUser();

    Livewire::test(Memberships::class, ['realm' => $community, 'username' => $someoneElse->getFirstAttribute('uid')])
        ->assertForbidden();
});

test('a realm admin can view another users memberships in their own realm', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsAdmin($community);

    Livewire::test(Memberships::class, ['realm' => $community, 'username' => $target->username])
        ->assertStatus(200);
});

test('a realm admin cannot view memberships in a different realm', function (): void {
    $adminRealm = newCommunity();
    $otherRealm = newCommunity();
    $target = TestLdap::member($otherRealm);
    actingAsAdmin($adminRealm);

    Livewire::test(Memberships::class, ['realm' => $otherRealm, 'username' => $target->username])
        ->assertForbidden();
});

test('the profile picture page renders for the owner', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Picture::class, ['realm' => $community, 'username' => $user->username])
        ->assertStatus(200);
});

test('a user cannot open someone else picture page', function (): void {
    $community = newCommunity();
    actingAsMember($community);
    $someoneElse = TestLdap::makeUser();

    Livewire::test(Picture::class, ['realm' => $community, 'username' => $someoneElse->getFirstAttribute('uid')])
        ->assertForbidden();
});

test('a realm admin can open another users picture page in their own realm', function (): void {
    $community = newCommunity();
    $target = TestLdap::member($community);
    actingAsAdmin($community);

    Livewire::test(Picture::class, ['realm' => $community, 'username' => $target->username])
        ->assertStatus(200);
});

test('a realm admin cannot open a picture page in a different realm', function (): void {
    $adminRealm = newCommunity();
    $otherRealm = newCommunity();
    $target = TestLdap::member($otherRealm);
    actingAsAdmin($adminRealm);

    Livewire::test(Picture::class, ['realm' => $otherRealm, 'username' => $target->username])
        ->assertForbidden();
});

test('uploading and cropping a picture saves it, without a separate save-canvas upload step', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Picture::class, ['realm' => $community, 'username' => $user->username])
        ->set('upload', UploadedFile::fake()->image('face.jpg', 200, 200))
        ->assertHasNoErrors()
        ->set('cropX', 10)
        ->set('cropY', 10)
        ->set('cropWidth', 100)
        ->set('cropHeight', 100)
        ->call('savePicture')
        ->assertRedirect(route('profile.picture', ['realm' => $community->getShortCode(), 'username' => $user->username]));

    expect(ProfilePicture::where('user', $user->username)->where('realm', $community->getShortCode())->exists())->toBeTrue();
});

test('an invalid file type is rejected and never reaches storage', function (): void {
    $community = newCommunity();
    $user = actingAsMember($community);

    Livewire::test(Picture::class, ['realm' => $community, 'username' => $user->username])
        ->set('upload', UploadedFile::fake()->create('not-an-image.pdf', 10, 'application/pdf'))
        ->assertHasErrors(['upload']);

    expect(ProfilePicture::where('user', $user->username)->where('realm', $community->getShortCode())->exists())->toBeFalse();
});

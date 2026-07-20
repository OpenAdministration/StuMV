<?php

use App\Models\ProfilePicture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\Passport;
use Tests\Support\TestLdap;

/**
 * The legacy delegated-user endpoint (/api-legacy/user, served by SocialiteUser)
 * is still consumed by StuFis via its "stumv" Socialite driver. Its "avatar"
 * claim (Socialite's standard avatar claim, read by the Passport driver) must
 * be a URL, matching Directory\Users - the raw base64 jpegPhoto used to be
 * dumped into the JSON body, which breaks response()->json() and StuFis's
 * normalizeUrl().
 */
uses(RefreshDatabase::class);

test('the legacy user endpoint returns a null avatar when the user has no photo', function (): void {
    $community = newCommunity();
    $user = TestLdap::member($community);

    Passport::actingAs($user, ['profile']);

    $this->getJson('/api-legacy/user')
        ->assertOk()
        ->assertJson(['avatar' => null]);
});

test('the legacy user endpoint returns a public url to the profile picture', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    $user = TestLdap::member($community);

    Storage::disk('public')->put('avatars/some-file-id.webp', 'fake-image-contents');
    ProfilePicture::create(['user' => $user->username, 'realm' => $community->getShortCode(), 'file_id' => 'some-file-id']);

    Passport::actingAs($user, ['profile']);

    $this->getJson('/api-legacy/user')
        ->assertOk()
        ->assertJson(['avatar' => asset('storage/avatars/some-file-id.webp')]);
});

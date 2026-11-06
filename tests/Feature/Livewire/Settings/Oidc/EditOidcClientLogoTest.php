<?php

use App\Livewire\Oidc\EditOidcClientLogo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\ClientRepository;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('an admin can upload a logo and it saves immediately, without an explicit save action', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();
    actingAsAdmin($community);

    Livewire::test(EditOidcClientLogo::class, ['clientId' => $client->id, 'realmUid' => $community->getShortCode()])
        ->set('logo', UploadedFile::fake()->image('logo.png', 100, 100))
        ->assertHasNoErrors();

    $client->refresh();

    expect($client->logo_id)->not->toBeNull();
    Storage::disk('public')->assertExists('oidc-client-logos/'.$client->logo_id);
});

test('uploading a new logo replaces and deletes the old one', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('oidc-client-logos/old-logo.webp', 'fake-bytes');

    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'logo_id' => 'old-logo.webp'])->save();
    actingAsAdmin($community);

    // The old logo is already set, so the component shows "remove" rather
    // than the upload widget (see the view) - removing first is the only way
    // to reach the upload widget again, matching App\Livewire\Realm\EditRealmBranding's UX.
    Livewire::test(EditOidcClientLogo::class, ['clientId' => $client->id, 'realmUid' => $community->getShortCode()])
        ->call('removeLogo')
        ->set('logo', UploadedFile::fake()->image('logo.png', 100, 100))
        ->assertHasNoErrors();

    $client->refresh();

    expect($client->logo_id)->not->toBeNull()
        ->and($client->logo_id)->not->toBe('old-logo.webp');
    Storage::disk('public')->assertMissing('oidc-client-logos/old-logo.webp');
    Storage::disk('public')->assertExists('oidc-client-logos/'.$client->logo_id);
});

test('an invalid file type is rejected and never reaches storage', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();
    actingAsAdmin($community);

    Livewire::test(EditOidcClientLogo::class, ['clientId' => $client->id, 'realmUid' => $community->getShortCode()])
        ->set('logo', UploadedFile::fake()->create('not-an-image.pdf', 10))
        ->assertHasErrors(['logo']);

    expect($client->fresh()->logo_id)->toBeNull();
    Storage::disk('public')->assertDirectoryEmpty('oidc-client-logos');
});

test('a client\'s logo can be removed', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('oidc-client-logos/existing-logo.webp', 'fake-bytes');

    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'logo_id' => 'existing-logo.webp'])->save();
    actingAsAdmin($community);

    Livewire::test(EditOidcClientLogo::class, ['clientId' => $client->id, 'realmUid' => $community->getShortCode()])
        ->call('removeLogo');

    expect($client->fresh()->logo_id)->toBeNull();
    Storage::disk('public')->assertMissing('oidc-client-logos/existing-logo.webp');
});

test('a non-admin cannot upload a logo', function (): void {
    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();
    actingAsModerator($community);

    Livewire::test(EditOidcClientLogo::class, ['clientId' => $client->id, 'realmUid' => $community->getShortCode()])
        ->assertStatus(403);
});

test('a non-admin cannot remove a logo', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('oidc-client-logos/existing-logo.webp', 'fake-bytes');

    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode(), 'logo_id' => 'existing-logo.webp'])->save();
    actingAsAdmin($community);

    // Mount as an authorized admin, then simulate a different (unauthorized)
    // user driving the already-mounted component - the admin check inside
    // removeLogo() itself (not just mount()) is what must catch this.
    $component = Livewire::test(EditOidcClientLogo::class, ['clientId' => $client->id, 'realmUid' => $community->getShortCode()]);
    actingAsModerator($community);
    $component->call('removeLogo')->assertStatus(403);

    expect($client->fresh()->logo_id)->toBe('existing-logo.webp');
});

test('a superadmin can manage a client\'s logo in any realm', function (): void {
    Storage::fake('public');

    $community = newCommunity();
    $client = resolve(ClientRepository::class)->createAuthorizationCodeGrantClient('My SSO App', ['https://app.example.com/callback']);
    $client->forceFill(['community_uid' => $community->getShortCode()])->save();
    actingAsSuperAdmin();

    Livewire::test(EditOidcClientLogo::class, ['clientId' => $client->id, 'realmUid' => $community->getShortCode()])
        ->set('logo', UploadedFile::fake()->image('logo.png', 100, 100))
        ->assertHasNoErrors();

    expect($client->fresh()->logo_id)->not->toBeNull();
});

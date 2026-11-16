<?php

use App\Ldap\Community;
use App\Livewire\Settings\EditRealmBranding;
use App\Models\RealmBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

afterEach(function (): void {
    RealmBranding::where('realm', 'demo')->delete();
});

test('an admin can upload a logo and it saves immediately, without an explicit save action', function (): void {
    actingAsAdmin('demo');

    Livewire::test(EditRealmBranding::class, ['realm' => Community::findByUid('demo')])
        ->set('logo', UploadedFile::fake()->image('logo.png', 100, 100))
        ->assertHasNoErrors();

    expect(RealmBranding::where('realm', 'demo')->value('logo_id'))->not->toBeNull();
});

test('a raster logo is scaled down and stored losslessly for print', function (): void {
    Storage::fake('public');
    actingAsAdmin('demo');

    Livewire::test(EditRealmBranding::class, ['realm' => Community::findByUid('demo')])
        ->set('logo', UploadedFile::fake()->image('logo.png', 3000, 1000))
        ->assertHasNoErrors();

    $stored = Storage::disk('public')->get('realm-branding/'.RealmBranding::where('realm', 'demo')->value('logo_id'));

    // VP8L is the lossless WebP bitstream
    expect(getimagesizefromstring($stored))->toMatchArray([0 => 2048, 1 => 683])
        ->and(substr($stored, 12, 4))->toBe('VP8L');
});

test('an invalid file type is rejected and never reaches storage', function (): void {
    actingAsAdmin('demo');

    Livewire::test(EditRealmBranding::class, ['realm' => Community::findByUid('demo')])
        ->set('logo', UploadedFile::fake()->create('not-an-image.pdf', 10))
        ->assertHasErrors(['logo']);

    expect(RealmBranding::where('realm', 'demo')->exists())->toBeFalse();
});

test('an admin can save and enable a login notice', function (): void {
    actingAsAdmin('demo');

    Livewire::test(EditRealmBranding::class, ['realm' => Community::findByUid('demo')])
        ->set('login_notice_enabled', true)
        ->set('login_notice', '<p>Hallo <em>Welt</em></p><script>alert(1)</script>')
        ->call('saveLoginNotice')
        ->assertHasNoErrors();

    $branding = RealmBranding::forRealm('demo');
    expect($branding->login_notice_enabled)->toBeTrue()
        ->and($branding->login_notice)->toBe('<p>Hallo <em>Welt</em></p>');

    Livewire::test(EditRealmBranding::class, ['realm' => Community::findByUid('demo')])
        ->assertSet('login_notice_enabled', true)
        ->assertSet('login_notice', '<p>Hallo <em>Welt</em></p>');
});

test('toggling the login notice switch saves immediately', function (): void {
    actingAsAdmin('demo');

    $component = Livewire::test(EditRealmBranding::class, ['realm' => Community::findByUid('demo')])
        ->set('login_notice', '<p>Ungespeichert</p>')
        ->set('login_notice_enabled', true)
        ->assertHasNoErrors();

    expect(RealmBranding::forRealm('demo')->login_notice_enabled)->toBeTrue()
        ->and(RealmBranding::forRealm('demo')->login_notice)->toBeNull();

    $component->set('login_notice_enabled', false);

    expect(RealmBranding::forRealm('demo')->login_notice_enabled)->toBeFalse();
});

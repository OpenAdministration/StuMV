<?php

use App\Ldap\Community;
use App\Livewire\Realm\EditRealmBranding;
use App\Models\RealmBranding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

test('an invalid file type is rejected and never reaches storage', function (): void {
    actingAsAdmin('demo');

    Livewire::test(EditRealmBranding::class, ['realm' => Community::findByUid('demo')])
        ->set('logo', UploadedFile::fake()->create('not-an-image.pdf', 10))
        ->assertHasErrors(['logo']);

    expect(RealmBranding::where('realm', 'demo')->exists())->toBeFalse();
});

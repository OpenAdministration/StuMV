<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Models\RealmBranding;
use Flux\Flux;
use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

class EditRealmBranding extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $realm_uid;

    // Explicit raster whitelist rather than the 'image' rule - it accepts
    // formats (e.g. avif) that aren't necessarily previewable via Livewire's
    // temporaryUrl() (see config/livewire.php's preview_mimes) - must stay in
    // sync with that list.
    #[Validate(['file', 'mimes:jpg,jpeg,png,gif,webp,bmp,svg', 'max:5120'])]
    public $logo = null;

    #[Validate(['file', 'mimes:jpg,jpeg,png,gif,webp,bmp', 'max:10240'])]
    public $background = null;

    public function mount(Community $realm): void
    {
        $this->authorize('edit', $realm);
        $this->realm_uid = $realm->getShortCode();
    }

    public function render()
    {
        $branding = RealmBranding::forRealm($this->realm_uid);

        return view('livewire.realm.edit-branding', [
            'logoID' => $branding?->logo_id,
            'backgroundID' => $branding?->background_id,
        ])->title(__('realms.branding.title', ['realm' => $this->realm_uid]));
    }

    public function updatedLogo(): void
    {
        // Explicit, rather than relying on #[Validate]'s own on-update
        // validation to run (and throw) before this hook fires - that
        // ordering isn't guaranteed, and an unvalidated file reaching
        // rasterize() surfaces as an uncaught exception instead of a
        // validation error.
        $this->validateOnly('logo');
        $this->saveLogo();
    }

    private function saveLogo(): void
    {
        // SVG logos are stored as-is - Intervention/GD can only rasterize
        // bitmap formats, and a logo is commonly a vector graphic (the app's
        // own default logo is SVG too).
        if (strtolower($this->logo->getClientOriginalExtension()) === 'svg') {
            $filename = Str::uuid().'.svg';
            $this->logo->storePubliclyAs('realm-branding', $filename, 'public');
        } else {
            $filename = Str::uuid().'.webp';
            Storage::disk('public')->put(
                'realm-branding/'.$filename,
                $this->rasterize($this->logo, width: 512, height: 512),
            );
        }

        $this->replaceFile('logo_id', $filename);
        $this->reset('logo');

        Flux::toast(variant: 'success', text: __('realms.branding.logo_saved'));
    }

    public function deleteLogo(): void
    {
        $this->deleteFile('logo_id');

        Flux::toast(variant: 'success', text: __('realms.branding.logo_removed'));
    }

    public function updatedBackground(): void
    {
        $this->validateOnly('background');
        $this->saveBackground();
    }

    private function saveBackground(): void
    {
        $filename = Str::uuid().'.webp';
        Storage::disk('public')->put(
            'realm-branding/'.$filename,
            $this->rasterize($this->background, width: 1920),
        );

        $this->replaceFile('background_id', $filename);
        $this->reset('background');

        Flux::toast(variant: 'success', text: __('realms.branding.background_saved'));
    }

    public function deleteBackground(): void
    {
        $this->deleteFile('background_id');

        Flux::toast(variant: 'success', text: __('realms.branding.background_removed'));
    }

    /**
     * Decode, downscale (never up), and re-encode an uploaded raster image to
     * WebP bytes.
     */
    private function rasterize($file, ?int $width = null, ?int $height = null): string
    {
        $img = Image::fromPath($file->getRealPath());
        $img->scale($width, $height);

        return $img->toWebp()->toBytes();
    }

    private function replaceFile(string $column, string $newFilename): void
    {
        $branding = RealmBranding::firstOrNew(['realm' => $this->realm_uid]);
        $oldFilename = $branding->{$column};
        $branding->{$column} = $newFilename;
        $branding->save();

        if ($oldFilename) {
            Storage::disk('public')->delete('realm-branding/'.$oldFilename);
        }
    }

    private function deleteFile(string $column): void
    {
        $branding = RealmBranding::forRealm($this->realm_uid);
        if (! $branding || ! $branding->{$column}) {
            return;
        }

        Storage::disk('public')->delete('realm-branding/'.$branding->{$column});
        $branding->{$column} = null;
        $branding->save();
    }
}

<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Facades\Image;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shared by NewOidcClient/EditOidcClient - both need the exact same logo
 * storage behavior (SVG kept as-is, everything else rasterized to WebP; same
 * folder, same max dimensions), so keeping it in one place is what stops
 * them from drifting out of sync, mirroring App\Livewire\Realm\EditRealmBranding's
 * own logo handling.
 */
trait StoresOidcClientLogo
{
    private function storeOidcClientLogo($upload): string
    {
        if (strtolower((string) $upload->getClientOriginalExtension()) === 'svg') {
            $filename = Str::uuid().'.svg';
            $upload->storePubliclyAs('oidc-client-logos', $filename, 'public');

            return $filename;
        }

        $filename = Str::uuid().'.webp';
        Storage::disk('public')->put(
            'oidc-client-logos/'.$filename,
            Image::fromPath($upload->getRealPath())->scale(width: 512, height: 512)->toWebp()->toBytes(),
        );

        return $filename;
    }

    private function deleteOidcClientLogo(?string $filename): void
    {
        if ($filename) {
            Storage::disk('public')->delete('oidc-client-logos/'.$filename);
        }
    }
}

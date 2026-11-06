<?php

namespace App\Livewire\Oidc;

use App\Ldap\Community;
use App\Livewire\Concerns\StoresOidcClientLogo;
use App\Models\PassportClient;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * A logo upload combined with other deferred text fields in the same
 * Livewire component/form crashes client-side on Livewire v4.3.3 - the
 * upload-finish commit corrupts the component's reactive state, wiping every
 * other field (see EditOidcClient/NewOidcClient's own history). Isolating it
 * as its own child component - its own wire:id, its own instant save/remove
 * actions, no other deferred fields sharing its commit cycle - sidesteps the
 * issue entirely, mirroring how App\Livewire\Realm\EditRealmBranding already
 * handles logo/background uploads.
 */
class EditOidcClientLogo extends Component
{
    use StoresOidcClientLogo;
    use WithFileUploads;

    #[Locked]
    public string $clientId;

    #[Locked]
    public string $realmUid;

    #[Validate(['file', 'mimes:svg,png,jpg,jpeg,webp', 'max:5120'])]
    public $logo = null;

    public function mount(string $clientId, string $realmUid): void
    {
        $this->clientId = $clientId;
        $this->realmUid = $realmUid;
        $this->authorizeAdmin();
    }

    public function render()
    {
        return view('livewire.oidc.edit-oidc-client-logo', [
            'logoId' => $this->client()->logo_id,
        ]);
    }

    public function updatedLogo(): void
    {
        // Explicit, rather than relying on #[Validate]'s own on-update
        // validation to run (and throw) before this hook fires - that
        // ordering isn't guaranteed, and an unvalidated file reaching
        // storeOidcClientLogo() would surface as an uncaught exception
        // instead of a validation error (same reasoning as
        // App\Livewire\Realm\EditRealmBranding::updatedLogo()).
        $this->validateOnly('logo');
        $this->authorizeAdmin();

        $client = $this->client();
        $this->deleteOidcClientLogo($client->logo_id);
        $client->forceFill(['logo_id' => $this->storeOidcClientLogo($this->logo)])->save();
        $this->reset('logo');

        Flux::toast(variant: 'success', text: __('oidc_clients.logo_saved'));
    }

    public function removeLogo(): void
    {
        $this->authorizeAdmin();

        $client = $this->client();
        $this->deleteOidcClientLogo($client->logo_id);
        $client->forceFill(['logo_id' => null])->save();

        Flux::toast(variant: 'success', text: __('oidc_clients.logo_removed'));
    }

    /**
     * Re-derived on every action (not cached in mount()) - $clientId/$realmUid
     * are #[Locked] so a client can't tamper with them post-mount, but this
     * still guards against the two ever referring to different realms.
     */
    private function client(): PassportClient
    {
        return PassportClient::where('community_uid', $this->realmUid)->findOrFail($this->clientId);
    }

    /**
     * Mirrors App\Http\Middleware\CommunityAdmin exactly - this component has
     * no route/middleware of its own (it's only ever embedded inside
     * EditOidcClient's already realm-admin-gated page), so each action
     * re-checks independently rather than solely trusting the parent's
     * context at mount time.
     */
    private function authorizeAdmin(): void
    {
        $user = auth()->user();
        $community = Community::findByUid($this->realmUid);
        abort_unless($user?->can('admin', $community) || $user?->ldap()->isSuperAdmin(), 403);
    }
}

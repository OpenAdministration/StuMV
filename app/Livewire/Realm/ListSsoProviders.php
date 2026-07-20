<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Models\RealmSsoProvider;
use Flux\Flux;
use Livewire\Component;

class ListSsoProviders extends Component
{
    public string $uid;

    public ?int $deleteProviderId = null;

    public string $deleteProviderName = '';

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getShortCode();
    }

    public function render()
    {
        $providers = RealmSsoProvider::where('realm', $this->uid)->orderBy('name')->get();

        return view('livewire.realm.list-sso-providers', ['providers' => $providers])
            ->title(__('sso_providers.list_title'));
    }

    public function deletePrepare(int $providerId): void
    {
        $provider = RealmSsoProvider::where('realm', $this->uid)->findOrFail($providerId);
        $this->deleteProviderId = $provider->id;
        $this->deleteProviderName = $provider->name;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $provider = RealmSsoProvider::where('realm', $this->uid)->findOrFail($this->deleteProviderId);
        $provider->delete();

        Flux::toast(variant: 'success', text: __('sso_providers.deleted_success'));
        $this->closeDelete();
    }

    public function closeDelete(): void
    {
        Flux::modal('delete')->close();
        unset($this->deleteProviderId, $this->deleteProviderName);
    }
}

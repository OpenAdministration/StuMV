<?php

namespace App\Livewire\IdentityProvider;

use App\Ldap\Community;
use App\Models\RealmIdentityProvider;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListIdentityProviders extends Component
{
    use WithPagination;

    public string $uid;

    #[Url]
    public string $sortField = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    public ?string $deleteProviderId = null;

    public string $deleteProviderName = '';

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getShortCode();
    }

    public function sortBy($field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
            $this->sortField = $field;
        }
    }

    public function render()
    {
        $providers = RealmIdentityProvider::where('realm', $this->uid)
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.identity-provider.list-identity-providers', ['providers' => $providers])
            ->title(__('identity_providers.list_title'));
    }

    public function deletePrepare(string $providerId): void
    {
        $provider = RealmIdentityProvider::where('realm', $this->uid)->findOrFail($providerId);
        $this->deleteProviderId = $provider->id;
        $this->deleteProviderName = $provider->name;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $provider = RealmIdentityProvider::where('realm', $this->uid)->findOrFail($this->deleteProviderId);
        $provider->delete();

        Flux::toast(variant: 'success', text: __('identity_providers.deleted_success'));
        $this->closeDelete();
    }

    public function closeDelete(): void
    {
        Flux::modal('delete')->close();
        unset($this->deleteProviderId, $this->deleteProviderName);
    }
}

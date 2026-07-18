<?php

namespace App\Livewire\Oidc;

use App\Models\PassportClient;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListOidcClients extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    public string $revokeClientId = '';

    public string $revokeClientName = '';

    public string $deleteClientId = '';

    public string $deleteClientName = '';

    public function sortBy($field): void
    {
        if ($this->sortField === $field) {
            // toggle direction
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
            $this->sortField = $field;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        // OIDC/SSO clients authenticate a delegated end user rather than
        // querying one community's directory data, so - unlike the Directory
        // API's clients - they aren't bound to a community_uid.
        $clients = PassportClient::whereNull('community_uid')
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.oidc.list-oidc-clients', ['clients' => $clients])
            ->title(__('oidc_clients.list_title'));
    }

    public function revokePrepare(string $clientId): void
    {
        $client = PassportClient::whereNull('community_uid')->findOrFail($clientId);
        $this->revokeClientId = $client->id;
        $this->revokeClientName = $client->name;
        Flux::modal('revoke')->show();
    }

    public function revokeCommit(): void
    {
        $client = PassportClient::whereNull('community_uid')->findOrFail($this->revokeClientId);

        $client->tokens()->with('refreshToken')->each(function ($token): void {
            $token->refreshToken?->revoke();
            $token->revoke();
        });
        $client->forceFill(['revoked' => true])->save();

        Flux::toast(variant: 'success', text: __('oidc_clients.revoked_success'));
        $this->close();
    }

    public function close(): void
    {
        Flux::modal('revoke')->close();
        unset($this->revokeClientId, $this->revokeClientName);
    }

    public function deletePrepare(string $clientId): void
    {
        $client = PassportClient::whereNull('community_uid')->findOrFail($clientId);
        $this->deleteClientId = $client->id;
        $this->deleteClientName = $client->name;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $client = PassportClient::whereNull('community_uid')->findOrFail($this->deleteClientId);

        $client->authCodes()->delete();
        $client->tokens()->delete();
        $client->delete();

        Flux::toast(variant: 'success', text: __('oidc_clients.deleted_success'));
        $this->closeDelete();
    }

    public function closeDelete(): void
    {
        Flux::modal('delete')->close();
        unset($this->deleteClientId, $this->deleteClientName);
    }
}

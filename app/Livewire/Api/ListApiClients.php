<?php

namespace App\Livewire\Api;

use App\Ldap\Community;
use App\Models\PassportClient;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListApiClients extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    public string $uid;

    public string $revokeClientId = '';

    public string $revokeClientName = '';

    public string $deleteClientId = '';

    public string $deleteClientName = '';

    public function mount(Community $realm)
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getFirstAttribute('ou');
    }

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

    /**
     * OIDC clients now also carry a community_uid, so grant_types (not just
     * community_uid) is what actually distinguishes the two client kinds
     * sharing this table.
     */
    private function scopeToRealmApiClients()
    {
        return PassportClient::where('community_uid', $this->uid)
            ->whereJsonContains('grant_types', 'client_credentials');
    }

    public function render()
    {
        $clients = $this->scopeToRealmApiClients()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.api.list-api-clients', ['clients' => $clients])
            ->title(__('api_clients.list_title'));
    }

    public function revokePrepare(string $clientId): void
    {
        $client = $this->scopeToRealmApiClients()->findOrFail($clientId);
        $this->revokeClientId = $client->id;
        $this->revokeClientName = $client->name;
        Flux::modal('revoke')->show();
    }

    public function revokeCommit(): void
    {
        $client = $this->scopeToRealmApiClients()->findOrFail($this->revokeClientId);

        $client->tokens()->with('refreshToken')->each(function ($token): void {
            $token->refreshToken?->revoke();
            $token->revoke();
        });
        $client->forceFill(['revoked' => true])->save();

        Flux::toast(variant: 'success', text: __('api_clients.revoked_success'));
        $this->close();
    }

    public function close(): void
    {
        Flux::modal('revoke')->close();
        unset($this->revokeClientId, $this->revokeClientName);
    }

    public function deletePrepare(string $clientId): void
    {
        $client = $this->scopeToRealmApiClients()->findOrFail($clientId);
        $this->deleteClientId = $client->id;
        $this->deleteClientName = $client->name;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $client = $this->scopeToRealmApiClients()->findOrFail($this->deleteClientId);

        $client->authCodes()->delete();
        $client->tokens()->delete();
        $client->delete();

        Flux::toast(variant: 'success', text: __('api_clients.deleted_success'));
        $this->closeDelete();
    }

    public function closeDelete(): void
    {
        Flux::modal('delete')->close();
        unset($this->deleteClientId, $this->deleteClientName);
    }
}

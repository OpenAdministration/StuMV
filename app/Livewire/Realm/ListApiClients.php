<?php

namespace App\Livewire\Realm;

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

    public function mount(Community $realm)
    {
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

    public function render()
    {
        $clients = PassportClient::where('community_uid', $this->uid)
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(10);

        return view('livewire.realm.list-api-clients', ['clients' => $clients])
            ->title(__('api_clients.list_title'));
    }

    public function revokePrepare(string $clientId): void
    {
        $client = PassportClient::where('community_uid', $this->uid)->findOrFail($clientId);
        $this->revokeClientId = $client->id;
        $this->revokeClientName = $client->name;
        Flux::modal('revoke')->show();
    }

    public function revokeCommit(): void
    {
        $client = PassportClient::where('community_uid', $this->uid)->findOrFail($this->revokeClientId);

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
}

<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Models\PassportClient;
use Flux\Flux;
use Livewire\Component;

class ListApiClients extends Component
{
    public string $uid;

    public string $revokeClientId = '';

    public string $revokeClientName = '';

    public function mount(Community $realm)
    {
        $this->uid = $realm->getFirstAttribute('ou');
    }

    public function render()
    {
        $clients = PassportClient::where('community_uid', $this->uid)
            ->orderBy('name')
            ->get();

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

<?php

namespace App\Livewire\Settings\Oidc;

use App\Ldap\Community;
use App\Models\PassportClient;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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
    /** @var 'asc'|'desc' */
    public string $sortDirection = 'asc';

    public string $uid;

    public string $revokeClientId = '';

    public string $revokeClientName = '';

    public string $deleteClientId = '';

    public string $deleteClientName = '';

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getFirstAttribute('ou');
    }

    public function sortBy(string $field): void
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
     * OIDC clients now carry a community_uid like the Directory API's
     * clients do, so grant_types (not community_uid) is what actually
     * distinguishes the two client kinds sharing the same table.
     *
     * @return Builder<PassportClient>
     */
    private function scopeToRealmOidcClients(): Builder
    {
        return PassportClient::where('community_uid', $this->uid)
            ->whereJsonContains('grant_types', 'authorization_code');
    }

    public function render(): View
    {
        $clients = $this->scopeToRealmOidcClients()
            ->when($this->search !== '', fn ($query) => $query->whereLike('name', '%'.$this->search.'%'))
            ->orderBy($this->sortField, $this->sortDirection === 'desc' ? 'desc' : 'asc')
            ->paginate(10);

        return view('livewire.settings.oidc.list-oidc-clients', ['clients' => $clients])
            ->title(__('oidc_clients.list_title'));
    }

    public function revokePrepare(string $clientId): void
    {
        $client = $this->scopeToRealmOidcClients()->findOrFail($clientId);
        $this->revokeClientId = $client->id;
        $this->revokeClientName = $client->name;
        Flux::modal('revoke')->show();
    }

    public function revokeCommit(): void
    {
        $client = $this->scopeToRealmOidcClients()->findOrFail($this->revokeClientId);

        $client->tokens()->with('refreshToken')->each(function ($token): void {
            $token->refreshToken?->revoke();
            $token->revoke();
        });
        $client->forceFill(['revoked' => true])->save();
        $client->recordAction('revoked');

        Flux::toast(variant: 'success', text: __('oidc_clients.revoked_success'));
        $this->close();
    }

    public function close(): void
    {
        Flux::modal('revoke')->close();
        $this->reset('revokeClientId', 'revokeClientName');
    }

    public function deletePrepare(string $clientId): void
    {
        $client = $this->scopeToRealmOidcClients()->findOrFail($clientId);
        $this->deleteClientId = $client->id;
        $this->deleteClientName = $client->name;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $client = $this->scopeToRealmOidcClients()->findOrFail($this->deleteClientId);

        $client->authCodes()->delete();
        $client->tokens()->delete();
        $client->delete();
        $client->recordAction('deleted');

        Flux::toast(variant: 'success', text: __('oidc_clients.deleted_success'));
        $this->closeDelete();
    }

    public function closeDelete(): void
    {
        Flux::modal('delete')->close();
        $this->reset('deleteClientId', 'deleteClientName');
    }
}

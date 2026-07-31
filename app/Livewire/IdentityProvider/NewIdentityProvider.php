<?php

namespace App\Livewire\IdentityProvider;

use App\Ldap\Community;
use App\Models\RealmIdentityProvider;
use Flux\Flux;
use Livewire\Component;

class NewIdentityProvider extends Component
{
    public string $uid = '';

    public string $name = '';

    public string $issuer = '';

    public string $client_id = '';

    public string $client_secret = '';

    public string $groups_claim = 'groups';

    public bool $enabled = true;

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getShortCode();
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:2|max:255',
            'issuer' => 'required|url|max:255',
            'client_id' => 'required|string|max:255',
            'client_secret' => 'required|string|max:255',
            'groups_claim' => 'required|string|max:255',
            'enabled' => 'boolean',
        ];
    }

    public function render()
    {
        return view('livewire.identity-provider.new-identity-provider')->title(__('identity_providers.new_title'));
    }

    public function save()
    {
        $this->validate();

        RealmIdentityProvider::create([
            'realm' => $this->uid,
            'name' => $this->name,
            'issuer' => rtrim($this->issuer, '/'),
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'groups_claim' => $this->groups_claim,
            'enabled' => $this->enabled,
        ]);

        Flux::toast(variant: 'success', text: __('identity_providers.created_success'));

        return to_route('realms.identity-providers', ['realm' => $this->uid]);
    }
}

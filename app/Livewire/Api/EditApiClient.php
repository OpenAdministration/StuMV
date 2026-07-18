<?php

namespace App\Livewire\Api;

use App\Ldap\Community;
use App\Models\PassportClient;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Laravel\Passport\ClientRepository;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EditApiClient extends Component
{
    #[Locked]
    public string $clientId;

    public string $uid = '';

    public string $name = '';

    public array $scopes = [];

    public function mount(Community $realm, PassportClient $client)
    {
        $this->uid = $realm->getFirstAttribute('ou');

        abort_if($client->community_uid !== $this->uid, 404);

        $this->clientId = $client->id;
        $this->name = $client->name;
        $this->scopes = $client->scopes ?? [];
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'scopes' => 'required|array|min:1',
            'scopes.*' => Rule::in(NewApiClient::AVAILABLE_SCOPES),
        ];
    }

    public function render()
    {
        return view('livewire.api.edit-api-client')->title(__('api_clients.edit_title'));
    }

    public function save(ClientRepository $clients)
    {
        $this->validate();

        $client = PassportClient::where('community_uid', $this->uid)->findOrFail($this->clientId);

        $clients->update($client, $this->name, []);
        $client->forceFill([
            'scopes' => array_values($this->scopes),
        ])->save();

        Flux::toast(variant: 'success', text: __('api_clients.edit_success'));

        $this->redirect(route('realms.api-clients', ['realm' => $this->uid]), navigate: true);
    }
}

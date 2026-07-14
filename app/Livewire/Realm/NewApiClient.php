<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Models\PassportClient;
use Illuminate\Validation\Rule;
use Laravel\Passport\ClientRepository;
use Livewire\Component;

class NewApiClient extends Component
{
    public const AVAILABLE_SCOPES = ['committees', 'groups', 'users'];

    public string $name = '';

    public array $scopes = [];

    public string $uid = '';

    public ?string $createdClientId = null;

    public ?string $createdClientSecret = null;

    public function mount(Community $realm)
    {
        $this->uid = $realm->getFirstAttribute('ou');
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'scopes' => 'required|array|min:1',
            'scopes.*' => Rule::in(self::AVAILABLE_SCOPES),
        ];
    }

    public function render()
    {
        return view('livewire.realm.new-api-client')->title(__('api_clients.new_title'));
    }

    public function save(ClientRepository $clients)
    {
        $this->validate();

        /** @var PassportClient $client */
        $client = $clients->createClientCredentialsGrantClient($this->name);
        $client->forceFill([
            'community_uid' => $this->uid,
            'scopes' => array_values($this->scopes),
        ])->save();

        $this->createdClientId = $client->id;
        $this->createdClientSecret = $client->plainSecret;
    }
}

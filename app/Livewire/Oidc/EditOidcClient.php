<?php

namespace App\Livewire\Oidc;

use App\Models\PassportClient;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Laravel\Passport\ClientRepository;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EditOidcClient extends Component
{
    #[Locked]
    public string $clientId;

    public string $name = '';

    public string $redirectUris = '';

    public array $scopes = [];

    public function mount(PassportClient $client)
    {
        abort_if($client->community_uid !== null, 404);

        $this->clientId = $client->id;
        $this->name = $client->name;
        $this->redirectUris = implode("\n", $client->redirect_uris ?? []);
        $this->scopes = $client->scopes ?? [];
    }

    protected function redirectUriList(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $this->redirectUris))
            ->map(fn ($uri) => trim($uri))
            ->filter()
            ->values()
            ->all();
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'redirectUris' => ['required', function ($attribute, $value, $fail): void {
                $uris = $this->redirectUriList();
                if (empty($uris)) {
                    $fail(__('oidc_clients.redirect_uris_required'));

                    return;
                }
                foreach ($uris as $uri) {
                    if (! filter_var($uri, FILTER_VALIDATE_URL)) {
                        $fail(__('oidc_clients.redirect_uri_invalid', ['uri' => $uri]));

                        return;
                    }
                }
            }],
            'scopes' => 'required|array|min:1',
            'scopes.*' => Rule::in(NewOidcClient::AVAILABLE_SCOPES),
        ];
    }

    public function render()
    {
        return view('livewire.oidc.edit-oidc-client')->title(__('oidc_clients.edit_title'));
    }

    public function save(ClientRepository $clients)
    {
        $this->validate();

        $client = PassportClient::whereNull('community_uid')->findOrFail($this->clientId);

        $clients->update($client, $this->name, $this->redirectUriList());
        $client->forceFill([
            'scopes' => array_values($this->scopes),
        ])->save();

        Flux::toast(variant: 'success', text: __('oidc_clients.edit_success'));

        $this->redirect(route('oidc-clients.list'), navigate: true);
    }
}

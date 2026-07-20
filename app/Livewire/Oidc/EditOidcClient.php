<?php

namespace App\Livewire\Oidc;

use App\Ldap\Community;
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

    public string $uid = '';

    public string $name = '';

    public string $redirectUris = '';

    public array $scopes = [];

    public bool $requiresConsent = false;

    public function mount(Community $realm, PassportClient $client): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getFirstAttribute('ou');

        abort_if($client->community_uid !== $this->uid || ! $client->hasGrantType('authorization_code'), 404);

        $this->clientId = $client->id;
        $this->name = $client->name;
        $this->redirectUris = implode("\n", $client->redirect_uris ?? []);
        $this->scopes = $client->scopes ?? [];
        $this->requiresConsent = $client->requires_consent;
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
            'requiresConsent' => 'boolean',
        ];
    }

    public function render()
    {
        return view('livewire.oidc.edit-oidc-client')->title(__('oidc_clients.edit_title'));
    }

    public function save(ClientRepository $clients)
    {
        $this->validate();

        $client = PassportClient::where('community_uid', $this->uid)->findOrFail($this->clientId);
        $scopesChanged = collect($client->scopes ?? [])->sort()->values()->all() !== collect($this->scopes)->sort()->values()->all();

        $clients->update($client, $this->name, $this->redirectUriList());
        $client->forceFill([
            'scopes' => array_values($this->scopes),
            'requires_consent' => $this->requiresConsent,
        ])->save();

        // A user's prior approval is remembered for as long as they hold a
        // non-revoked token whose granted scopes already cover what's
        // requested (App\Models\PassportClient::skipsAuthorization()) -
        // revoking existing tokens here is what makes that memory expire as
        // soon as this client's scopes actually change, forcing everyone
        // back through the consent screen next time.
        if ($scopesChanged) {
            $client->tokens()->with('refreshToken')->each(function ($token): void {
                $token->refreshToken?->revoke();
                $token->revoke();
            });
        }

        Flux::toast(variant: 'success', text: __('oidc_clients.edit_success'));

        $this->redirect(route('realms.oidc-clients', ['realm' => $this->uid]), navigate: true);
    }
}

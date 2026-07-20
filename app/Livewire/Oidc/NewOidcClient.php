<?php

namespace App\Livewire\Oidc;

use App\Ldap\Community;
use App\Models\PassportClient;
use Illuminate\Validation\Rule;
use Laravel\Passport\ClientRepository;
use Livewire\Component;

class NewOidcClient extends Component
{
    public const AVAILABLE_SCOPES = ['openid', 'profile', 'email', 'phone', 'address', 'committees', 'groups', 'users'];

    public string $name = '';

    public string $redirectUris = '';

    public array $scopes = ['openid', 'profile', 'email', 'groups'];

    public bool $requiresConsent = true;

    public string $uid = '';

    public ?string $createdClientId = null;

    public ?string $createdClientSecret = null;

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getFirstAttribute('ou');
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
            'scopes.*' => Rule::in(self::AVAILABLE_SCOPES),
            'requiresConsent' => 'boolean',
        ];
    }

    public function render()
    {
        return view('livewire.oidc.new-oidc-client')->title(__('oidc_clients.new_title'));
    }

    public function save(ClientRepository $clients)
    {
        $this->validate();

        /** @var PassportClient $client */
        $client = $clients->createAuthorizationCodeGrantClient($this->name, $this->redirectUriList());
        $client->forceFill([
            'community_uid' => $this->uid,
            'scopes' => array_values($this->scopes),
            'requires_consent' => $this->requiresConsent,
        ])->save();

        $this->createdClientId = $client->id;
        $this->createdClientSecret = $client->plainSecret;
    }
}

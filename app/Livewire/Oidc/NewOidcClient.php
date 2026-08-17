<?php

namespace App\Livewire\Oidc;

use App\Ldap\Community;
use App\Models\PassportClient;
use Illuminate\Validation\Rule;
use Laravel\Passport\ClientRepository;
use Livewire\Component;

class NewOidcClient extends Component
{
    public const AVAILABLE_SCOPES = ['openid', 'profile', 'email', 'phone', 'address', 'groups'];

    public string $name = '';

    public string $description = '';

    public string $redirectUris = '';

    public array $scopes = ['openid', 'profile', 'email', 'groups'];

    public bool $requiresConsent = true;

    public bool $disableClientAuthentication = false;

    public string $backChannelLogoutUri = '';

    public string $postLogoutRedirectUris = '';

    public string $uid = '';

    public bool $created = false;

    public ?string $createdClientId = null;

    public ?string $createdClientSecret = null;

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getFirstAttribute('ou');
    }

    protected function redirectUriList(): array
    {
        return self::splitUriList($this->redirectUris);
    }

    protected function postLogoutRedirectUriList(): array
    {
        return self::splitUriList($this->postLogoutRedirectUris);
    }

    private static function splitUriList(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value))
            ->map(fn ($uri) => trim($uri))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $uris
     */
    private static function validateUriList(array $uris, callable $fail, bool $allowWildcard = false): void
    {
        foreach ($uris as $uri) {
            if (! $allowWildcard && str_contains($uri, '*')) {
                $fail(__('oidc_clients.redirect_uri_wildcard', ['uri' => $uri]));

                return;
            }

            if (! filter_var($uri, FILTER_VALIDATE_URL)) {
                $fail(__('oidc_clients.redirect_uri_invalid', ['uri' => $uri]));

                return;
            }
        }
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:3|max:255',
            'description' => 'nullable|string|max:1000',
            'redirectUris' => ['required', function ($attribute, $value, $fail): void {
                $uris = $this->redirectUriList();
                if (empty($uris)) {
                    $fail(__('oidc_clients.redirect_uris_required'));

                    return;
                }
                self::validateUriList($uris, $fail);
            }],
            'scopes' => 'required|array|min:1',
            'scopes.*' => Rule::in(self::AVAILABLE_SCOPES),
            'requiresConsent' => 'boolean',
            'disableClientAuthentication' => 'boolean',
            'backChannelLogoutUri' => 'nullable|url',
            'postLogoutRedirectUris' => [function ($attribute, $value, $fail): void {
                self::validateUriList($this->postLogoutRedirectUriList(), $fail, allowWildcard: true);
            }],
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
        $client = $clients->createAuthorizationCodeGrantClient(
            $this->name,
            $this->redirectUriList(),
            confidential: ! $this->disableClientAuthentication,
        );
        // Service provider/imprint/terms/privacy links are deliberately not
        // part of this form - like the logo (see EditOidcClientLogo's doc
        // comment), they're set-up-later, edit-only fields rather than
        // required at creation time.
        $client->forceFill([
            'community_uid' => $this->uid,
            'scopes' => array_values($this->scopes),
            'requires_consent' => $this->requiresConsent,
            'back_channel_logout_uri' => $this->backChannelLogoutUri ?: null,
            'post_logout_redirect_uris' => $this->postLogoutRedirectUriList() ?: null,
            'description' => $this->description ?: null,
        ])->save();

        $this->created = true;
        $this->createdClientId = $client->id;
        $this->createdClientSecret = $client->plainSecret;
    }
}

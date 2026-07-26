<?php

namespace App\Livewire\Oidc;

use App\Ldap\Community;
use App\Models\PassportClient;
use Flux\Flux;
use Illuminate\Support\Str;
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

    public bool $disableClientAuthentication = false;

    public string $backChannelLogoutUri = '';

    public string $postLogoutRedirectUris = '';

    public ?string $regeneratedSecret = null;

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
        $this->disableClientAuthentication = ! $client->confidential();
        $this->backChannelLogoutUri = $client->back_channel_logout_uri ?? '';
        $this->postLogoutRedirectUris = implode("\n", $client->post_logout_redirect_uris ?? []);
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
            'redirectUris' => ['required', function ($attribute, $value, $fail): void {
                $uris = $this->redirectUriList();
                if (empty($uris)) {
                    $fail(__('oidc_clients.redirect_uris_required'));

                    return;
                }
                self::validateUriList($uris, $fail);
            }],
            'scopes' => 'required|array|min:1',
            'scopes.*' => Rule::in(NewOidcClient::AVAILABLE_SCOPES),
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
        return view('livewire.oidc.edit-oidc-client')->title(__('oidc_clients.edit_title'));
    }

    public function save(ClientRepository $clients)
    {
        $this->validate();

        $client = PassportClient::where('community_uid', $this->uid)->findOrFail($this->clientId);
        $scopesChanged = collect($client->scopes ?? [])->sort()->values()->all() !== collect($this->scopes)->sort()->values()->all();
        $wasConfidential = $client->confidential();
        $staysConfidential = ! $this->disableClientAuthentication;

        $clients->update($client, $this->name, $this->redirectUriList());
        $client->forceFill([
            'scopes' => array_values($this->scopes),
            'requires_consent' => $this->requiresConsent,
            'back_channel_logout_uri' => $this->backChannelLogoutUri ?: null,
            'post_logout_redirect_uris' => $this->postLogoutRedirectUriList() ?: null,
        ]);

        // A hashed secret can't be recovered to show again if client
        // authentication is re-enabled - only a brand new one can be issued,
        // same as at creation time.
        if ($staysConfidential && ! $wasConfidential) {
            $client->secret = $newSecret = Str::random(40);
        } elseif (! $staysConfidential && $wasConfidential) {
            $client->secret = null;
        }

        $client->save();

        // A user's prior approval is remembered for as long as they hold a
        // non-revoked token whose granted scopes already cover what's
        // requested (App\Models\PassportClient::skipsAuthorization()) -
        // revoking existing tokens here is what makes that memory expire as
        // soon as this client's scopes actually change, forcing everyone
        // back through the consent screen next time. Tokens issued before a
        // client authentication change are revoked too: a refresh token
        // minted while public would otherwise silently start requiring a
        // secret it was never given (or vice versa), failing cryptically
        // instead of just prompting a fresh login.
        if ($scopesChanged || $wasConfidential !== $staysConfidential) {
            $client->tokens()->with('refreshToken')->each(function ($token): void {
                $token->refreshToken?->revoke();
                $token->revoke();
            });
        }

        // A regenerated secret can only ever be shown this once - stay on
        // the page to reveal it instead of redirecting straight back to the
        // client list like every other save.
        if (isset($newSecret)) {
            $this->regeneratedSecret = $newSecret;

            return;
        }

        Flux::toast(variant: 'success', text: __('oidc_clients.edit_success'));

        $this->redirect(route('realms.oidc-clients', ['realm' => $this->uid]), navigate: true);
    }
}

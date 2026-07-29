<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\RealmIdentityProvider;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use App\Support\IdentityProviderGroupRoleSync;
use App\Support\OidcProviderFactory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use League\OAuth2\Client\Provider\GenericProvider;

class OidcLoginController extends Controller
{
    public function __construct(private readonly OidcProviderFactory $providerFactory) {}

    /**
     * Send the visitor to the external identity provider's own login page.
     */
    public function redirect(Community $realm, RealmIdentityProvider $provider): RedirectResponse
    {
        $this->authorizeProvider($realm, $provider);

        $oauthProvider = $this->buildProvider($realm, $provider);

        $authorizationUrl = $oauthProvider->getAuthorizationUrl([
            'scope' => 'openid email profile',
            ...$provider->extra_authorize_params ?? [],
        ]);

        session([
            'identity_provider_state' => $oauthProvider->getState(),
            'identity_provider_id' => $provider->id,
        ]);

        return redirect($authorizationUrl);
    }

    /**
     * Handle the identity provider's redirect back. Matches the returned
     * email against an existing account in this realm (and logs it in
     * directly - the external IdP already vouched for the address, so no
     * LDAP bind is needed here), or hands off to the "pick a username"
     * completion step for a brand-new account.
     */
    public function callback(Community $realm, RealmIdentityProvider $provider, Request $request)
    {
        $this->authorizeProvider($realm, $provider);

        $state = $request->query('state');
        $validState = $state
            && $state === session('identity_provider_state')
            && session('identity_provider_id') === $provider->id;

        session()->forget(['identity_provider_state', 'identity_provider_id']);

        abort_unless($validState, 400, 'Invalid or expired SSO login attempt.');
        abort_if($request->has('error'), 400, (string) $request->query('error_description', 'The identity provider reported an error.'));

        $oauthProvider = $this->buildProvider($realm, $provider);

        $token = $oauthProvider->getAccessToken('authorization_code', [
            'code' => $request->query('code'),
        ]);

        $claims = $oauthProvider->getResourceOwner($token)->toArray();

        $email = $claims['email'] ?? null;
        abort_unless($email, 422, 'The identity provider did not return an email address.');

        $existing = User::where('email', $email)->where('realm', $realm->getShortCode())->first();

        if ($existing) {
            abort_if(
                LdapUser::isLockedByUsername($existing->username, $realm->peopleDn()),
                403,
                'This account has been locked.'
            );

            Auth::login($existing);
            $request->session()->regenerate();

            resolve(IdentityProviderGroupRoleSync::class)->apply($provider, $existing->username, $claims);

            return redirect()->intended(RouteServiceProvider::home($realm->getShortCode()));
        }

        session(['identity_provider_pending' => [
            'realm' => $realm->getShortCode(),
            'provider_id' => $provider->id,
            'email' => $email,
            'given_name' => $claims['given_name'] ?? '',
            'family_name' => $claims['family_name'] ?? '',
            'claims' => $claims,
        ]]);

        return to_route('identity-provider.register', ['realm' => $realm->getShortCode()]);
    }

    private function authorizeProvider(Community $realm, RealmIdentityProvider $provider): void
    {
        abort_unless($provider->enabled && $provider->realm === $realm->getShortCode(), 404);
    }

    private function buildProvider(Community $realm, RealmIdentityProvider $provider): GenericProvider
    {
        $discovery = $this->discover($provider->issuer);

        return $this->providerFactory->make([
            'clientId' => $provider->client_id,
            'clientSecret' => $provider->client_secret,
            'redirectUri' => route('identity-provider.callback', ['realm' => $realm->getShortCode(), 'provider' => $provider->id]),
            'urlAuthorize' => $discovery['authorization_endpoint'],
            'urlAccessToken' => $discovery['token_endpoint'],
            'urlResourceOwnerDetails' => $discovery['userinfo_endpoint'],
        ]);
    }

    private function discover(string $issuer): array
    {
        return Cache::remember('identity-provider-discovery:'.md5($issuer), now()->addHour(), fn (): array => Http::get(rtrim($issuer, '/').'/.well-known/openid-configuration')->throw()->json());
    }
}

<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Models\PassportClient;
use App\Support\EndsAuthenticatedSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Throwable;

/**
 * OpenID Connect RP-Initiated Logout 1.0: a client redirects the user's
 * browser here (optionally carrying id_token_hint/client_id/
 * post_logout_redirect_uri/state) to end their StuMV session, then get
 * redirected back. Advertised as end_session_endpoint by
 * RealmDiscoveryController. Deliberately outside the "auth" middleware
 * group (like jwks/discovery) - per spec, this must also work for a
 * visitor whose local session has already expired or never existed.
 */
class EndSessionController extends Controller
{
    public function __invoke(Request $request, Community $realm, EndsAuthenticatedSession $endsSession): RedirectResponse
    {
        $client = $this->resolveClient($request, $realm);
        $postLogoutRedirectUri = $this->validatedRedirectUri($request, $client);

        if (! Auth::guest()) {
            $endsSession->end($request);
        }

        if ($postLogoutRedirectUri === null) {
            return redirect(Community::loginUrlFor($realm->getShortCode()));
        }

        return redirect($this->withState($postLogoutRedirectUri, $request));
    }

    /**
     * id_token_hint is preferred over the bare client_id param (spoofable by
     * anyone, since it's just a query string) - RECOMMENDED by the spec
     * precisely so the client can't be impersonated by a third party
     * crafting the logout link.
     */
    private function resolveClient(Request $request, Community $realm): ?PassportClient
    {
        $clientId = $this->clientIdFromIdTokenHint($request) ?? $request->string('client_id')->toString() ?: null;

        if ($clientId === null) {
            return null;
        }

        return PassportClient::where('community_uid', $realm->getShortCode())->find($clientId);
    }

    /**
     * Verifies only the id_token_hint's signature, not its expiry - per
     * spec (§2.4), a client may hint with an already-expired ID Token, and
     * the OP is expected to still honor it for this purpose.
     */
    private function clientIdFromIdTokenHint(Request $request): ?string
    {
        $hint = $request->string('id_token_hint')->toString();

        if (! $hint) {
            return null;
        }

        try {
            $config = Configuration::forAsymmetricSigner(
                new Sha256,
                InMemory::file(Passport::keyPath('oauth-private.key')),
                InMemory::file(Passport::keyPath('oauth-public.key')),
            );

            $token = $config->parser()->parse($hint);

            if (! $config->validator()->validate($token, new SignedWith($config->signer(), $config->verificationKey()))) {
                return null;
            }

            $audience = $token->claims()->get('aud');
            $clientId = is_array($audience) ? ($audience[0] ?? null) : $audience;

            return $clientId !== null ? (string) $clientId : null;
        } catch (Throwable) {
            // A malformed/unparseable id_token_hint is just a hint that
            // failed - not a reason to fail the whole request.
            return null;
        }
    }

    /**
     * Only ever returns a URI matching one the resolved client itself
     * registered (App\Models\PassportClient::post_logout_redirect_uris) - an
     * unknown client, no client at all, or any URI matching nothing means
     * falling back to the realm's own login page instead (see __invoke()),
     * since honoring an arbitrary post_logout_redirect_uri would be an open
     * redirect. Unlike the OAuth redirect_uri (exact match only, enforced by
     * league/oauth2-server itself), a registered entry may use `*` as a
     * wildcard - Str::is() - since this comparison is entirely our own code,
     * not governed by the OAuth2 spec's stricter redirect_uri rules.
     */
    private function validatedRedirectUri(Request $request, ?PassportClient $client): ?string
    {
        $requested = $request->string('post_logout_redirect_uri')->toString();

        if (! $requested || ! $client) {
            return null;
        }

        if (! Str::is($client->post_logout_redirect_uris ?? [], $requested)) {
            return null;
        }

        return $requested;
    }

    private function withState(string $uri, Request $request): string
    {
        if (! $request->filled('state')) {
            return $uri;
        }

        return $uri.(str_contains($uri, '?') ? '&' : '?').'state='.urlencode($request->string('state')->toString());
    }
}

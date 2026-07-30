<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Models\PassportClient;
use App\Services\Oidc\AccessTokenVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Bridge\ClientRepository;

/**
 * RFC 7662 OAuth 2.0 Token Introspection.
 */
class IntrospectionController extends Controller
{
    public function __construct(
        private readonly AccessTokenVerifier $accessTokenVerifier,
        private readonly ClientRepository $clientRepository,
    ) {}

    public function __invoke(Request $request, Community $realm): JsonResponse
    {
        $clientId = (string) ($request->input('client_id') ?? $request->getUser());
        $clientSecret = (string) ($request->input('client_secret') ?? $request->getPassword());

        $client = PassportClient::find($clientId);

        if (! $client || $client->community_uid !== $realm->getShortCode()) {
            Log::warning('OIDC introspection rejected: unknown or wrong-realm client', ['client_id' => $clientId, 'realm' => $realm->getShortCode()]);

            return $this->invalidClient();
        }

        if (! $this->clientRepository->validateClient($clientId, $clientSecret, null)) {
            Log::warning('OIDC introspection rejected: client authentication failed', ['client_id' => $clientId, 'realm' => $realm->getShortCode()]);

            return $this->invalidClient();
        }

        $token = (string) $request->input('token', '');

        if ($token === '') {
            return response()->json(['error' => 'invalid_request'], 400);
        }

        $accessToken = $this->accessTokenVerifier->verify($token);

        if ($accessToken === null || ! $this->belongsToRealm($accessToken, $realm)) {
            // Same response either way: a token whose own client lives in a
            // different realm must not be distinguishable from one that's
            // simply invalid/expired/revoked - anything else would leak
            // cross-realm information about which token strings are "real".
            Log::info('OIDC introspection: token inactive', ['client_id' => $clientId, 'realm' => $realm->getShortCode()]);

            return response()->json(['active' => false]);
        }

        Log::info('OIDC introspection: token active', ['client_id' => $clientId, 'token_client_id' => $accessToken->oauth_client_id, 'realm' => $realm->getShortCode()]);

        return response()->json($this->activeResponse($accessToken, $realm));
    }

    /**
     * The calling client is already realm-checked above, but that only
     * proves the *caller* may use this realm's introspection endpoint - it
     * says nothing about which realm actually issued the token being
     * introspected. Without this check, a realm B client could learn that a
     * token belonging to a realm A client is active, its scopes, its user,
     * etc., since access tokens are all signed with the same server-wide
     * key regardless of realm.
     */
    private function belongsToRealm(AccessToken $accessToken, Community $realm): bool
    {
        $tokenClient = PassportClient::find($accessToken->oauth_client_id);

        return $tokenClient !== null && $tokenClient->community_uid === $realm->getShortCode();
    }

    /**
     * @return array<string, mixed>
     */
    private function activeResponse(AccessToken $accessToken, Community $realm): array
    {
        $response = [
            'active' => true,
            'scope' => implode(' ', $accessToken->oauth_scopes ?? []),
            'client_id' => $accessToken->oauth_client_id,
            'token_type' => 'Bearer',
            'iss' => Community::issuerFor($realm->getShortCode()),
            'aud' => $accessToken->oauth_client_id,
            'exp' => $accessToken->expires_at?->timestamp,
            'iat' => $accessToken->created_at?->timestamp,
        ];

        if (! empty($accessToken->oauth_user_id)) {
            $identity = resolve(config('openid.repositories.identity'))->getByIdentifier((string) $accessToken->oauth_user_id);

            if ($identity) {
                $response['sub'] = $identity->getIdentifier();
                $response['username'] = $identity->getClaims()['preferred_username'] ?? null;
            }
        }

        return array_filter($response, fn ($value) => $value !== null);
    }

    private function invalidClient(): JsonResponse
    {
        return response()->json(['error' => 'invalid_client'], 401);
    }
}

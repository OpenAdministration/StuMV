<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Models\PassportClient;
use App\Services\Oidc\AccessTokenVerifier;
use Defuse\Crypto\Crypto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Bridge\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;
use Throwable;

/**
 * RFC 7009 OAuth 2.0 Token Revocation - lets a client explicitly kill a
 * single access or refresh token it holds, rather than only ever being able
 * to wait out its lifetime or have an admin revoke the whole client.
 */
class RevocationController extends Controller
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
            Log::warning('OIDC revocation rejected: unknown or wrong-realm client', ['client_id' => $clientId, 'realm' => $realm->getShortCode()]);

            return $this->invalidClient();
        }

        if (! $this->clientRepository->validateClient($clientId, $clientSecret, null)) {
            Log::warning('OIDC revocation rejected: client authentication failed', ['client_id' => $clientId, 'realm' => $realm->getShortCode()]);

            return $this->invalidClient();
        }

        $token = (string) $request->input('token', '');

        if ($token === '') {
            return response()->json(['error' => 'invalid_request'], 400);
        }

        // §2.1: token_type_hint is only ever a hint to skip a wasted lookup -
        // if it's absent, wrong, or the token simply doesn't match that type,
        // the server MUST still try the other token type before giving up.
        $revoked = $request->input('token_type_hint') === 'refresh_token'
            ? $this->revokeRefreshToken($token, $client) || $this->revokeAccessToken($token, $client)
            : $this->revokeAccessToken($token, $client) || $this->revokeRefreshToken($token, $client);

        Log::info('OIDC revocation processed', ['client_id' => $clientId, 'realm' => $realm->getShortCode(), 'revoked' => $revoked]);

        // §2.2: always 200, even for a token that was already invalid,
        // unknown, or belonged to a different client - this endpoint must
        // never be usable to probe which tokens exist or who they belong to.
        return response()->json([], 200);
    }

    private function revokeAccessToken(string $token, PassportClient $client): bool
    {
        $accessToken = $this->accessTokenVerifier->verify($token);

        if ($accessToken === null || $accessToken->oauth_client_id !== $client->id) {
            return false;
        }

        return $accessToken->revoke();
    }

    /**
     * Passport refresh tokens aren't DB-lookup-able by their raw string (only
     * by the "refresh_token_id" they encrypt) - this replicates exactly the
     * decryption League\OAuth2\Server\Grant\RefreshTokenGrant::validateOldRefreshToken()
     * already does when a client exchanges one for a new access token,
     * against the same payload shape it writes
     * (League\OAuth2\Server\ResponseTypes\BearerTokenResponse::generateHttpResponse()):
     * {client_id, refresh_token_id, access_token_id, scopes, user_id, expire_time}.
     */
    private function revokeRefreshToken(string $token, PassportClient $client): bool
    {
        try {
            $payload = json_decode(Crypto::decryptWithPassword($token, Passport::tokenEncryptionKey(app('encrypter'))), true);
        } catch (Throwable) {
            return false;
        }

        if (! is_array($payload) || ($payload['client_id'] ?? null) !== $client->id || empty($payload['refresh_token_id'])) {
            return false;
        }

        RefreshToken::whereKey($payload['refresh_token_id'])->update(['revoked' => true]);

        // §2.1 recommends also invalidating other tokens from the same
        // authorization grant - matches Passport's own rotate-on-use
        // behaviour (RefreshTokenGrant revokes the old access token whenever
        // its refresh token is redeemed), just triggered by revocation
        // instead of reuse.
        if (! empty($payload['access_token_id'])) {
            Token::whereKey($payload['access_token_id'])->update(['revoked' => true]);
        }

        return true;
    }

    private function invalidClient(): JsonResponse
    {
        return response()->json(['error' => 'invalid_client'], 401);
    }
}

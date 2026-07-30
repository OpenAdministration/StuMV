<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Models\PassportClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Bridge\ClientRepository;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

/**
 * RFC 7662 OAuth 2.0 Token Introspection. Rather than re-implementing JWT
 * signature/expiry/revocation checks here, this builds a throwaway PSR-7
 * request carrying the token-to-introspect as its own Bearer header and
 * hands it to the same ResourceServer that every other authenticated API
 * request already goes through (see
 * Laravel\Passport\Guards\TokenGuard::getPsrRequestViaBearerToken()) - if
 * that succeeds, the token is currently valid; if it throws, it isn't.
 */
class IntrospectionController extends Controller
{
    public function __construct(
        private readonly ResourceServer $resourceServer,
        private readonly ClientRepository $clientRepository,
    ) {}

    public function __invoke(Request $request, Community $realm): JsonResponse
    {
        $clientId = (string) ($request->input('client_id') ?? $request->getUser());
        $clientSecret = (string) ($request->input('client_secret') ?? $request->getPassword());

        $client = PassportClient::find($clientId);

        if (! $client || $client->community_uid !== $realm->getShortCode()) {
            return $this->invalidClient();
        }

        if (! $this->clientRepository->validateClient($clientId, $clientSecret, null)) {
            return $this->invalidClient();
        }

        $token = (string) $request->input('token', '');

        if ($token === '') {
            return response()->json(['error' => 'invalid_request'], 400);
        }

        $accessToken = $this->introspect($token);

        if ($accessToken === null) {
            return response()->json(['active' => false]);
        }

        return response()->json($this->activeResponse($accessToken, $realm));
    }

    private function introspect(string $token): ?AccessToken
    {
        $psrRequest = (new PsrHttpFactory)->createRequest(
            Request::create('/', 'GET', server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token])
        );

        try {
            return AccessToken::fromPsrRequest($this->resourceServer->validateAuthenticatedRequest($psrRequest));
        } catch (OAuthServerException) {
            return null;
        }
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

<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Models\PassportClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenIDConnect\ClaimExtractor;

/**
 * Replacement for OpenIDConnect\Laravel\UserInfoController - that one stamps
 * `sub` from $request->user()->getAuthIdentifier(), the local `user.id`
 * primary key. App\Entities\IdentityEntity::setIdentifier() already uses the
 * LDAP entryUUID as this identity's identifier for the id_token's own `sub`
 * claim; a relying party is entitled to expect the same `sub` from both, so
 * this reuses $identity->getIdentifier() instead of duplicating that value.
 */
class UserInfoController extends Controller
{
    public function __invoke(Request $request, Community $realm, ClaimExtractor $claimExtractor): JsonResponse
    {
        $token = $request->user()->token();

        // 'auth:api' (applied on this route) only proves the token itself is
        // valid - not that it belongs to this realm. All access tokens are
        // signed with the same server-wide key regardless of realm, so
        // without this check, a token issued under realm A's client would
        // also resolve fine at realm B's own /oauth/userinfo URL.
        $client = PassportClient::find($token->client_id);
        abort_unless($client && $client->community_uid === $realm->getShortCode(), 403);

        $identity = resolve(config('openid.repositories.identity'))
            ->getByIdentifier((string) $request->user()->getAuthIdentifier());

        $claims = $claimExtractor->extract($token->scopes, $identity->getClaims());

        $claims['sub'] = $identity->getIdentifier();

        return response()->json($claims);
    }
}

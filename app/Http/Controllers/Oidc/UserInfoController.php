<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
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
    public function __invoke(Request $request, ClaimExtractor $claimExtractor): JsonResponse
    {
        $token = $request->user()->token();

        $identity = resolve(config('openid.repositories.identity'))
            ->getByIdentifier((string) $request->user()->getAuthIdentifier());

        $claims = $claimExtractor->extract($token->scopes, $identity->getClaims());

        $claims['sub'] = $identity->getIdentifier();

        return response()->json($claims);
    }
}

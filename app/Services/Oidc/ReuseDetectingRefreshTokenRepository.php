<?php

namespace App\Services\Oidc;

use Illuminate\Support\Facades\Log;
use Laravel\Passport\Bridge\RefreshTokenRepository as BaseRefreshTokenRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

/**
 * Refresh tokens are single-use (Passport::$revokeRefreshTokenAfterUse, see
 * PassportServiceProvider::makeAuthorizationServer()) - a previously-used one
 * being presented again is either a client that lost the new pair after a
 * network hiccup and is retrying, or a stolen refresh token being replayed
 * after the legitimate client already rotated past it. Either way, the
 * current descendant session can no longer be trusted as a single event, so
 * this revokes every token for that user+client (not just the one being
 * checked) rather than only failing the one request, the same "assume
 * compromise, force a fresh login" response most OIDC providers give reuse.
 * Bound in place of the vendor repository in
 * App\Providers\AppServiceProvider::register().
 */
class ReuseDetectingRefreshTokenRepository extends BaseRefreshTokenRepository
{
    #[\Override]
    public function isRefreshTokenRevoked(string $tokenId): bool
    {
        $refreshToken = Passport::refreshToken()->find($tokenId);

        if ($refreshToken === null) {
            return true;
        }

        if ($refreshToken->revoked) {
            $this->revokeTokenFamily($refreshToken);

            return true;
        }

        return false;
    }

    private function revokeTokenFamily(RefreshToken $reusedRefreshToken): void
    {
        $accessToken = $reusedRefreshToken->accessToken;

        if ($accessToken === null) {
            return;
        }

        Log::warning('OIDC refresh token reuse detected - revoking all tokens for this user/client', [
            'client_id' => $accessToken->client_id,
            'user_id' => $accessToken->user_id,
        ]);

        Token::where('client_id', $accessToken->client_id)
            ->where('user_id', $accessToken->user_id)
            ->where('revoked', false)
            ->with('refreshToken')
            ->each(function (Token $token): void {
                $token->refreshToken?->revoke();
                $token->revoke();
            });
    }
}

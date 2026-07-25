<?php

namespace App\Services\Oidc;

use Illuminate\Contracts\Auth\Authenticatable;
use OpenIDConnect\ClaimExtractor;

/**
 * For the OAuth consent screen: renders the actual per-user values that will
 * be released to a client for each scope it requests, not just a category
 * label. Runs claims through the same ClaimExtractor Passport itself uses to
 * build the id_token/userinfo response, so what's shown here matches what's
 * actually sent. Scopes with no claim set (e.g. "openid" itself, or the
 * directory-API-only "committees"/"users" scopes) yield no lines - the view
 * falls back to a static description for those.
 */
class ConsentScopeSummary
{
    public function __construct(
        private readonly ClaimExtractor $claimExtractor,
    ) {
    }

    /**
     * @param  \Laravel\Passport\Scope[]  $scopes
     * @return array<string, string[]> scope id => "Label: value" lines that will be shared
     */
    public function forScopes(Authenticatable $user, array $scopes): array
    {
        $identity = resolve(config('openid.repositories.identity'))
            ->getByIdentifier((string) $user->getAuthIdentifier());

        $claims = $identity->getClaims();

        $summary = [];

        foreach ($scopes as $scope) {
            $summary[$scope->id] = $this->describe($this->claimExtractor->extract([$scope->id], $claims));
        }

        return $summary;
    }

    /**
     * @param  array<string, mixed>  $claims
     * @return string[]
     */
    private function describe(array $claims): array
    {
        $lines = [];

        foreach (['name', 'given_name', 'family_name', 'preferred_username', 'picture'] as $key) {
            if (!empty($claims[$key])) {
                $lines[] = __("auth.claim_$key").': '.$claims[$key];
            }
        }

        if (!empty($claims['email'])) {
            $suffix = !empty($claims['email_verified']) ? ' ('.__('auth.claim_email_verified_suffix').')' : '';
            $lines[] = __('auth.claim_email').': '.$claims['email'].$suffix;
        }

        if (!empty($claims['phone_number'])) {
            $lines[] = __('auth.claim_phone_number').': '.$claims['phone_number'];
        }

        if ($address = $claims['address'] ?? null) {
            $line = implode(', ', array_filter([
                $address['street_address'] ?? null,
                $address['postal_code'] ?? null,
                $address['locality'] ?? null,
            ]));

            if ($line !== '') {
                $lines[] = __('auth.claim_address').': '.$line;
            }
        }

        if (!empty($claims['groups'])) {
            $lines[] = __('auth.claim_groups').': '.implode(', ', $claims['groups']);
        }

        return $lines;
    }
}

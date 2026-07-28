<?php

namespace App\Services\Oidc;

use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Scope;
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
    ) {}

    /**
     * @param  Scope[]  $scopes
     * @return array<string, array<int, array{label: string, value: string, image: bool}>> scope id => rows that will be shared
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
     * @return array<int, array{label: string, value: string, image: bool}>
     */
    private function describe(array $claims): array
    {
        $lines = [];

        foreach (['name', 'given_name', 'family_name', 'preferred_username'] as $key) {
            if (! empty($claims[$key])) {
                $lines[] = $this->row(__("auth.claim_$key"), $claims[$key]);
            }
        }

        if (! empty($claims['picture'])) {
            $lines[] = $this->row(__('auth.claim_picture'), $claims['picture'], image: true);
        }

        if (! empty($claims['email'])) {
            $suffix = ! empty($claims['email_verified']) ? ' ('.__('auth.claim_email_verified_suffix').')' : '';
            $lines[] = $this->row(__('auth.claim_email'), $claims['email'].$suffix);
        }

        if (! empty($claims['phone_number'])) {
            $lines[] = $this->row(__('auth.claim_phone_number'), $claims['phone_number']);
        }

        if ($address = $claims['address'] ?? null) {
            $line = implode(', ', array_filter([
                $address['street_address'] ?? null,
                $address['postal_code'] ?? null,
                $address['locality'] ?? null,
            ]));

            if ($line !== '') {
                $lines[] = $this->row(__('auth.claim_address'), $line);
            }
        }

        if (! empty($claims['groups'])) {
            $lines[] = $this->row(__('auth.claim_groups'), implode(', ', $claims['groups']));
        }

        return $lines;
    }

    /**
     * @return array{label: string, value: string, image: bool}
     */
    private function row(string $label, string $value, bool $image = false): array
    {
        return ['label' => $label, 'value' => $value, 'image' => $image];
    }
}

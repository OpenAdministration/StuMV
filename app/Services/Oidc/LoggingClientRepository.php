<?php

namespace App\Services\Oidc;

use Illuminate\Support\Facades\Log;
use Laravel\Passport\Bridge\ClientRepository as BaseClientRepository;

/**
 * League OAuth2 Server's own invalid_client response (thrown from
 * AbstractGrant::validateClient() when this returns false) is a deliberately
 * well-formed OAuth error - it never becomes an uncaught exception, so
 * Laravel's default exception logging never sees a failed client
 * authentication attempt at all. This logs *why* validateClient() rejected
 * one (client not found/revoked, no secret given, or secret mismatch) -
 * never the secret's actual value - so a real failure leaves a trace instead
 * of vanishing silently. Bound in place of the vendor repository in
 * App\Providers\AppServiceProvider::register().
 */
class LoggingClientRepository extends BaseClientRepository
{
    #[\Override]
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $record = $this->clients->findActive($clientIdentifier);

        if ($record === null) {
            Log::warning('OIDC client authentication failed: unknown or revoked client_id', [
                'client_id' => $clientIdentifier,
                'grant_type' => $grantType,
            ]);

            return false;
        }

        if (empty($clientSecret)) {
            Log::warning('OIDC client authentication failed: no client_secret was received', [
                'client_id' => $clientIdentifier,
                'grant_type' => $grantType,
            ]);

            return false;
        }

        if (! $this->hasher->check($clientSecret, $record->secret)) {
            Log::warning('OIDC client authentication failed: client_secret did not match', [
                'client_id' => $clientIdentifier,
                'grant_type' => $grantType,
            ]);

            return false;
        }

        return true;
    }
}

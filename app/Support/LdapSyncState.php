<?php

namespace App\Support;

use App\Ldap\Community;
use Illuminate\Support\Facades\Cache;

class LdapSyncState
{
    // Short on purpose: memberships expiring at midnight and direct LDAP edits don't invalidate it.
    private const int TTL_SECONDS = 900;

    public function __construct(private readonly LdapSyncDiff $diff) {}

    /**
     * @return 'synced'|'pending'
     */
    public function for(Community $realm): string
    {
        return Cache::remember(self::cacheKey($realm->getShortCode()), self::TTL_SECONDS, fn (): string => $this->diff->roleChanges($realm) === [] && $this->diff->groupChanges($realm) === []
            ? 'synced'
            : 'pending');
    }

    public static function forget(string $realm): void
    {
        Cache::forget(self::cacheKey($realm));
    }

    public static function forgetForGroupDn(string $groupDn): void
    {
        if (preg_match('/ou=Groups,ou=([^,]+),ou=Communities/i', $groupDn, $matches)) {
            self::forget($matches[1]);
        }
    }

    private static function cacheKey(string $realm): string
    {
        return "ldap-sync-state:{$realm}";
    }
}

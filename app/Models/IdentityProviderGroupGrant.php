<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records that App\Support\IdentityProviderGroupSync itself attached a user
 * to an LDAP group, since LDAP group membership (uniqueMember) has no room
 * for that kind of per-member provenance. Only rows recorded here are ever
 * candidates for that sync to later detach - a membership that pre-dates the
 * mapping, or was added by an admin or the role-derived ldap:sync-groups
 * command, has no grant row and is never touched.
 */
class IdentityProviderGroupGrant extends Model
{
    /**
     * @var array
     */
    protected $fillable = [
        'provider_id',
        'username',
        'group_dn',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(RealmIdentityProvider::class, 'provider_id');
    }
}

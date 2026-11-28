<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityProviderRoleMapping extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'external_group',
        'committee_dn',
        'role_cn',
    ];

    /** @return BelongsTo<RealmIdentityProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(RealmIdentityProvider::class, 'provider_id');
    }
}

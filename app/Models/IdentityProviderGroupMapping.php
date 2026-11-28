<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityProviderGroupMapping extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'provider_id',
        'external_group',
        'group_dn',
    ];

    /** @return BelongsTo<RealmIdentityProvider, $this> */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(RealmIdentityProvider::class, 'provider_id');
    }
}

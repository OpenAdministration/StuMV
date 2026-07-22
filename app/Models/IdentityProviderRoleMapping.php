<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityProviderRoleMapping extends Model
{
    use HasFactory;

    /**
     * @var array
     */
    protected $fillable = [
        'provider_id',
        'external_group',
        'committee_dn',
        'role_cn',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(RealmIdentityProvider::class, 'provider_id');
    }
}

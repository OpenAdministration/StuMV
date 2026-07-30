<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdentityProviderGroupMapping extends Model
{
    use HasFactory;

    /**
     * @var array
     */
    protected $fillable = [
        'provider_id',
        'external_group',
        'group_dn',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(RealmIdentityProvider::class, 'provider_id');
    }
}

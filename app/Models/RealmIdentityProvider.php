<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RealmIdentityProvider extends Model
{
    use HasFactory, HasUuids;

    /**
     * @var array
     */
    protected $fillable = [
        'realm',
        'name',
        'issuer',
        'client_id',
        'client_secret',
        'scopes',
        'enforce_email_verified',
        'groups_claim',
        'enabled',
    ];

    /**
     * Mirrors the column defaults, so a freshly created instance carries them
     * straight away rather than only after a refresh from the database.
     *
     * @var array
     */
    protected $attributes = [
        'scopes' => 'openid email profile',
        'enforce_email_verified' => true,
        'groups_claim' => 'groups',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'enforce_email_verified' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function roleMappings(): HasMany
    {
        return $this->hasMany(IdentityProviderRoleMapping::class, 'provider_id');
    }

    public function groupMappings(): HasMany
    {
        return $this->hasMany(IdentityProviderGroupMapping::class, 'provider_id');
    }
}

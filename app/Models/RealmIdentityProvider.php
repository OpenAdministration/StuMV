<?php

namespace App\Models;

use App\Models\Concerns\RecordsActions;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RealmIdentityProvider extends Model
{
    use HasFactory, HasUuids, RecordsActions;

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

    protected function activityLogName(): string
    {
        return 'identity_provider';
    }

    /** @return array{role_mappings: list<array<string, mixed>>, group_mappings: list<array<string, mixed>>} */
    public function mappingsForActivityLog(): array
    {
        return [
            'role_mappings' => $this->roleMappings()->get(['external_group', 'committee_dn', 'role_cn'])->toArray(),
            'group_mappings' => $this->groupMappings()->get(['external_group', 'group_dn'])->toArray(),
        ];
    }
}

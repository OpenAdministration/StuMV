<?php

namespace App\Models;

use App\Support\LdapSyncState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $group_dn
 * @property string $role_dn
 */
class GroupMembership extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'role_group_relation';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'group_dn',
        'role_dn',
    ];

    protected static function booted(): void
    {
        static::saved(fn (GroupMembership $m) => LdapSyncState::forgetForGroupDn((string) $m->group_dn));
        static::deleted(fn (GroupMembership $m) => LdapSyncState::forgetForGroupDn((string) $m->group_dn));
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}

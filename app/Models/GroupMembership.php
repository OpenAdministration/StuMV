<?php

namespace App\Models;

use App\Support\LdapSyncState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $group_id
 * @property Group $group
 * @property Role $role
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
     * @var array
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

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}

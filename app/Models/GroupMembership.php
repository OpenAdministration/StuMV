<?php

namespace App\Models;

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

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}

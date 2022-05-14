<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Role;
use App\Models\Group;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property integer $group_id
 * @property integer $role_id
 * @property Role $role
 * @property Group $group
 */
class GroupAssertion extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'group_assertion';

    /**
     * @var array
     */
    protected $fillable = [];

    /**
     * @return BelongsTo
     */
    public function role(): Relation
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo
     */
    public function group() : Relation
    {
        return $this->belongsTo(Group::class);
    }
}

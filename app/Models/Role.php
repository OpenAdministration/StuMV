<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Group;
use App\Models\RoleUserRelation;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property integer $id
 * @property integer $committee_id
 * @property string $name
 * @property Group[] $groups
 * @property Committee $committee
 * @property RoleUserRelation[] $users
 */
class Role extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'role';

    /**
     * @var array
     */
    protected $fillable = ['committee_id', 'name'];

    /**
     * @return BelongsToMany
     */
    public function groups(): Relation
    {
        return $this->belongsToMany(Group::class, 'group_role_relation');
    }

    /**
     * @return BelongsTo
     */
    public function committee(): Relation
    {
        return $this->belongsTo(Committee::class);
    }

    /**
     * @return HasMany
     */
    public function users(): Relation
    {
        return $this->hasMany(RoleUserRelation::class);
    }
}

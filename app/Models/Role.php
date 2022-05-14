<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Group;
use App\Models\RoleAssertion;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property integer $id
 * @property integer $gremium_id
 * @property string $name
 * @property Group[] $groups
 * @property Committee $gremium
 * @property RoleAssertion[] $roleAssertions
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
    protected $fillable = ['gremium_id', 'name'];

    /**
     * @return BelongsToMany
     */
    public function groups(): Relation
    {
        return $this->belongsToMany(Group::class, 'group_assertion');
    }

    /**
     * @return BelongsTo
     */
    public function gremium(): Relation
    {
        return $this->belongsTo(Committee::class);
    }

    /**
     * @return HasMany
     */
    public function roleAssertions(): Relation
    {
        return $this->hasMany(RoleAssertion::class);
    }
}

<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Group;
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
    use HasFactory;
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
    public function members(): Relation
    {
        return $this->hasMany(RoleUserRelation::class, 'role_id');
    }

    public function addMember(User $user, Carbon $start, Carbon $end = null)
    {
        $this->members()->save($user, ['from' => $start, 'until' => $end]);
        $this->refresh();
    }
}

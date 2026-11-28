<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property int $id
 * @property int $committee_id
 * @property string $name
 * @property Group[] $groups
 * @property Committee $committee
 * @property RoleMembership[] $users
 */
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'role';

    /**
     * @var list<string>
     */
    protected $fillable = ['committee_id', 'name'];

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): Relation
    {
        return $this->belongsToMany(Group::class, 'group_role_relation');
    }

    /**
     * @return BelongsTo<Committee, $this>
     */
    public function committee(): Relation
    {
        return $this->belongsTo(Committee::class);
    }

    /**
     * @return HasMany<RoleMembership, $this>
     */
    public function members(): Relation
    {
        return $this->hasMany(RoleMembership::class, 'role_id');
    }
}

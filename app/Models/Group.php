<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property integer $id
 * @property string $realm_uid
 * @property string $name
 * @property Realm $realm
 * @property Role[] $roles
 */
class Group extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'group';

    /**
     * @var array
     */
    protected $fillable = ['realm_uid', 'name'];

    /**
     * @return BelongsTo
     */
    public function realm(): Relation
    {
        return $this->belongsTo(Realm::class);
    }

    /**
     * @return BelongsToMany
     */
    public function roles(): Relation
    {
        return $this->belongsToMany(Role::class, 'group_role_relation');
    }
}

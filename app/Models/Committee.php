<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property integer $id
 * @property string $realm_uid
 * @property integer $parent_gremium_id
 * @property string $name
 * @property Committee $parentCommittee
 * @property Realm $realm
 * @property Role[] $roles
 */
class Committee extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'gremium';

    /**
     * @var array
     */
    protected $fillable = ['realm_uid', 'parent_gremium_id', 'name'];

    /**
     * @return BelongsTo
     */
    public function parentCommittee(): Relation
    {
        return $this->belongsTo(__CLASS__, 'parent_gremium_id');
    }

    /**
     * @return BelongsTo
     */
    public function realm() : Relation
    {
        return $this->belongsTo(Realm::class, 'realm_uid', 'uid');
    }

    /**
     * @return HasMany
     */
    public function roles(): Relation
    {
        return $this->hasMany(Role::class);
    }
}

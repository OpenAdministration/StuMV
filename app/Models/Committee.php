<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property integer $id
 * @property string $realm_uid
 * @property integer $parent_committee_id
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
    protected $table = 'committee';

    /**
     * @var array
     */
    protected $fillable = ['realm_uid', 'parent_committee_id', 'name'];

    /**
     * @return BelongsTo
     */
    public function parentCommittee(): Relation
    {
        return $this->belongsTo(__CLASS__, 'parent_committee_id');
    }

    /**
     * @return BelongsTo
     */
    public function realm() : Relation
    {
        return $this->belongsTo(Realm::class);
    }

    /**
     * @return HasMany
     */
    public function roles(): Relation
    {
        return $this->hasMany(Role::class);
    }
}

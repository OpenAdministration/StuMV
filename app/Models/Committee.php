<?php

namespace App\Models;

use Database\Factories\CommitteeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property int $id
 * @property string $realm_uid
 * @property int $parent_committee_id
 * @property string $name
 * @property Committee $parentCommittee
 * @property Realm $realm
 * @property Role[] $roles
 */
class Committee extends Model
{
    /** @use HasFactory<CommitteeFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'committee';

    /**
     * @var list<string>
     */
    protected $fillable = ['realm_uid', 'parent_committee_id', 'name'];

    /**
     * @return BelongsTo<self, $this>
     */
    public function parentCommittee(): Relation
    {
        return $this->belongsTo(self::class, 'parent_committee_id');
    }

    /** @return HasMany<self, $this> */
    public function childCommittee(): Relation
    {
        return $this->hasMany(self::class, 'parent_committee_id', 'id');
    }

    /**
     * @return BelongsTo<Realm, $this>
     */
    public function realm(): Relation
    {
        return $this->belongsTo(Realm::class);
    }

    /**
     * @return HasMany<Role, $this>
     */
    public function roles(): Relation
    {
        return $this->hasMany(Role::class);
    }
}

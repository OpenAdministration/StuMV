<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Domain;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property string $uid
 * @property string $long_name
 * @property Domain[] $domains
 * @property Committee[] $committees
 * @property Group[] $groups
 * @property User[] $admins
 * @property User[] $members
 */
class Realm extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'realm';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'uid';

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var array
     */
    protected $fillable = ['long_name'];

    /**
     * @return HasMany
     */
    public function domains(): Relation
    {
        return $this->hasMany(Domain::class, 'realm_uid', 'uid');
    }

    /**
     * @return HasMany
     */
    public function committee(): Relation
    {
        return $this->hasMany(Committee::class, 'realm_uid', 'uid');
    }

    /**
     * @return HasMany
     */
    public function groups(): Relation
    {
        return $this->hasMany(Group::class, 'realm_uid', 'uid');
    }

    /**
     * @return BelongsToMany
     */
    public function admins(): Relation
    {
        return $this->belongsToMany(User::class, 'realm_admin', 'realm_uid');
    }

    /**
     * @return BelongsToMany
     */
    public function members(): Relation
    {
        return $this->belongsToMany(User::class, 'realm_assertion', 'realm_uid');
    }
}

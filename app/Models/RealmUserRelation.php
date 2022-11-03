<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Realm;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property integer $user_id
 * @property string $realm_uid
 * @property Realm $realm
 * @property User $user
 */
class RealmUserRelation extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'realm_user_relation';

    /**
     * @var array
     */
    protected $fillable = [];

    /**
     * @return BelongsTo
     */
    public function realm(): Relation
    {
        return $this->belongsTo(Realm::class, 'realm_uid', 'uid');
    }

    /**
     * @return BelongsTo
     */
    public function user(): Relation
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property integer $role_id
 * @property integer $user_id
 * @property string $from
 * @property string $until
 * @property Role $role
 * @property User $user
 */
class RoleAssertion extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'role_assertion';

    /**
     * @var array
     */
    protected $fillable = ['role_id', 'user_id', 'from', 'until'];

    /**
     * @return BelongsTo
     */
    public function role(): Relation
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo
     */
    public function user(): Relation
    {
        return $this->belongsTo(User::class);
    }
}

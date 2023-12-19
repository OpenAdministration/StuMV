<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * @property integer $role_id
 * @property integer $user_id
 * @property string $from
 * @property string $until
 * @property Role $role
 * @property User $user
 */
class RoleMembership extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'role_user_relation';

    /**
     * @var array
     */
    protected $fillable = [
        'role_cn',
        'committee_dn',
        'username',
        'from',
        'until',
        'decided',
        'comment',
    ];

    protected $casts = [
        'from' => 'date',
        'until' => 'date',
        'decided' => 'date',
    ];

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
        return $this->belongsTo(User::class, 'username', 'username');
    }

    public function isActive() : bool {
        return Carbon::today()->betweenIncluded(
            $this->from->format('Y-m-d'),
            $this->until?->format('Y-m-d')
        );
    }

    public function scopeActive(Builder $query, Carbon $date = null)
    {
        if(is_null($date)){
            $date = today();
        }
        $query->whereDate('from', '<=', $date)
            ->where(function ($query) use ($date){
                $query->whereDate('until', '>=', $date)
                    ->orWhereNull('until');
            });
    }
}
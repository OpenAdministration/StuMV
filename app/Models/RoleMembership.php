<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;

/**
 * @property int $role_id
 * @property int $user_id
 * @property string $from
 * @property string $until
 * @property Role $role
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
        'realm',
        'username',
        'from',
        'until',
        'decided',
        'comment',
    ];

    /**
     * @return BelongsTo
     */
    public function role(): Relation
    {
        return $this->belongsTo(Role::class);
    }

    public function isActive(): bool
    {
        if ($this->until) {
            return Date::today()->betweenIncluded(
                $this->from->format('Y-m-d'),
                $this->until?->format('Y-m-d')
            );
        } else {
            return true;
        }
    }

    #[Scope]
    protected function active(Builder $query, ?Carbon $date = null)
    {
        if (is_null($date)) {
            $date = today();
        }
        $query->whereDate('from', '<=', $date)
            ->where(function ($query) use ($date): void {
                $query->whereDate('until', '>=', $date)
                    ->orWhereNull('until');
            });
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'from' => 'date',
            'until' => 'date',
            'decided' => 'date',
        ];
    }
}

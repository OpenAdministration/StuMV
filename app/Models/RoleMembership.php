<?php

namespace App\Models;

use App\Support\ActivityLogger;
use App\Support\LdapSyncState;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

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
        'identity_provider_id',
    ];

    protected static function booted(): void
    {
        static::saved(fn (RoleMembership $m) => LdapSyncState::forget((string) $m->realm));
        static::deleted(fn (RoleMembership $m) => LdapSyncState::forget((string) $m->realm));
    }

    /**
     * @return BelongsTo
     */
    public function role(): Relation
    {
        return $this->belongsTo(Role::class);
    }

    public function identityProvider(): BelongsTo
    {
        return $this->belongsTo(RealmIdentityProvider::class, 'identity_provider_id');
    }

    public function isActive(): bool
    {
        return $this->from !== null
            && $this->from->lte(Date::today())
            && ($this->until === null || $this->until->gte(Date::today()));
    }

    /**
     * Logs role_membership.$event: all fields, or with $before only those that changed.
     *
     * @param  ?array<string, ?string>  $before  from activityLogAttributes() before the change
     */
    public function recordAction(string $event, ?array $before = null, ?RealmIdentityProvider $actor = null): void
    {
        $changes = $before === null ? $this->activityLogAttributes() : ActivityLogger::diff($before, $this->activityLogAttributes());
        if ($before !== null && $changes === []) {
            return;
        }

        $committee = Str::after(Str::before((string) $this->committee_dn, ','), '=');
        resolve(ActivityLogger::class)->record(
            "role_membership.{$event}",
            $this->realm ?: null,
            $this,
            $changes,
            "{$this->role_cn} ({$committee})",
            $actor,
        );
    }

    /** Deletes the membership and logs which person left which role. */
    public function deleteAndRecord(): void
    {
        $this->delete();
        $this->recordAction('deleted');
    }

    /** @return array<string, ?string> */
    public function activityLogAttributes(): array
    {
        return [
            // entryUUID; a username alone is ambiguous across realms
            'person' => User::where('username', $this->username)->where('realm', $this->realm)->value('uid'),
            'role' => "cn={$this->role_cn},{$this->committee_dn}",
            'from' => $this->from?->toDateString(),
            'until' => $this->until?->toDateString(),
            'decided' => $this->decided?->toDateString(),
            'comment' => $this->comment,
        ];
    }

    #[Scope]
    protected function active(Builder $query, ?Carbon $date = null)
    {
        $date ??= today();
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

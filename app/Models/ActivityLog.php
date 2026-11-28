<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use LogicException;

/**
 * One change a person or the system made, written by App\Support\ActivityLogger.
 *
 * @property ?array<string, mixed> $details
 */
class ActivityLog extends Model
{
    use MassPrunable;

    public const null UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'realm',
        'actor_type',
        'actor_id',
        'action',
        'subject_type',
        'subject_id',
        'subject_label',
        'details',
        'ip',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Activity log entries are never changed.'));
    }

    /**
     * Points every reference to $oldDn, or to an entry below it, at $newDn after a move or rename.
     * Returns the number of entries changed.
     */
    public static function relocateDn(string $oldDn, string $newDn): int
    {
        $updated = 0;
        static::query()->whereNotNull('details')->chunkById(500, function ($logs) use ($oldDn, $newDn, &$updated): void {
            foreach ($logs as $log) {
                if ($log->details === null) {
                    continue;
                }
                $details = self::relocateIn($log->details, $oldDn, $newDn);
                if ($details !== $log->details) {
                    // past the guard against changes, only references move
                    static::query()->whereKey($log->getKey())->toBase()->update(['details' => json_encode($details)]);
                    $updated++;
                }
            }
        });

        return $updated;
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<array-key, mixed>
     */
    private static function relocateIn(array $values, string $oldDn, string $newDn): array
    {
        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $values[$key] = self::relocateIn($value, $oldDn, $newDn);
            } elseif (is_string($value) && strcasecmp($value, $oldDn) === 0) {
                $values[$key] = $newDn;
            } elseif (is_string($value) && str_ends_with(strtolower($value), ','.strtolower($oldDn))) {
                $values[$key] = substr($value, 0, -strlen($oldDn)).$newDn;
            }
        }

        return $values;
    }

    /** @return Builder<static> */
    public function prunable(): Builder
    {
        $days = config('activity_log.retention_days');
        $loginDays = config('activity_log.login_retention_days');

        return static::query()->where(function (Builder $query) use ($days, $loginDays): void {
            $query->whereRaw('1 = 0')
                ->when($days !== null, fn (Builder $q) => $q->orWhere('created_at', '<', Date::now()->subDays($days)))
                ->when($loginDays !== null, fn (Builder $q) => $q->orWhere(fn (Builder $q) => $q
                    ->where('action', 'like', 'login.%')
                    ->where('created_at', '<', Date::now()->subDays($loginDays))));
        });
    }

    protected function casts(): array
    {
        return [
            'details' => 'array',
            'created_at' => 'datetime',
        ];
    }
}

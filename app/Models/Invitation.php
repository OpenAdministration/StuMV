<?php

namespace App\Models;

use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Invitation extends Model
{
    protected $fillable = [
        'realm',
        'email',
        'invited_by_username',
        'expires_at',
        'accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function roleSelections(): HasMany
    {
        return $this->hasMany(InvitationRoleSelection::class);
    }

    /**
     * Logs invitation.$event, named after the invited email address.
     *
     * @param  array<string, mixed>  $details
     */
    public function recordAction(string $event, array $details): void
    {
        resolve(ActivityLogger::class)->record("invitation.{$event}", $this->realm, $this, $details, $this->email);
    }

    /** @return array{email: string, roles: list<string>, expires_at: ?string} */
    public function activityLogAttributes(): array
    {
        return [
            'email' => $this->email,
            'roles' => $this->roleSelections->map(fn (InvitationRoleSelection $s): string => "cn={$s->role_cn},{$s->committee_dn}")->values()->all(),
            'expires_at' => $this->expires_at?->toDateTimeString(),
        ];
    }

    public static function freshExpiry(): Carbon
    {
        return now()->addDays(7);
    }
}

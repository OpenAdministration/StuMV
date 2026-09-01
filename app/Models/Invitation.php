<?php

namespace App\Models;

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

    public static function freshExpiry(): Carbon
    {
        return now()->addDays(7);
    }
}

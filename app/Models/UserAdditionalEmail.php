<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tracks confirmation of an account's additional email addresses. Only a
 * confirmed one is written to LDAP "mail" (see App\Ldap\User), which is what
 * identity-provider account matching reads; an unconfirmed one exists here
 * and nowhere else.
 */
class UserAdditionalEmail extends Model
{
    protected $fillable = [
        'username',
        'realm',
        'address',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    protected function scopeVerified(Builder $query): Builder
    {
        return $query->whereNotNull('verified_at');
    }

    protected function scopeForAccount(Builder $query, string $username, string $realm): Builder
    {
        return $query->where('username', $username)->where('realm', $realm);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}

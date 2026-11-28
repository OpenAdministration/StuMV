<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * An inactive account whose owner was asked to log in to keep it.
 *
 * @property string $ldap_uid
 * @property string $realm
 * @property string $username
 * @property string $email
 * @property Carbon $notified_at
 * @property Carbon $delete_after
 */
class AccountDeletionMark extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'ldap_uid',
        'realm',
        'username',
        'email',
        'notified_at',
        'delete_after',
    ];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
            'delete_after' => 'datetime',
        ];
    }
}

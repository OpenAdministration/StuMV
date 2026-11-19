<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An inactive account whose owner was asked to log in to keep it. */
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

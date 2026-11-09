<?php

namespace App\Models;

use App\Support\MailmanListSyncStatus;
use Illuminate\Database\Eloquent\Model;

class GroupMailmanList extends Model
{
    protected $table = 'group_mailman_lists';

    protected $fillable = [
        'realm',
        'group_dn',
        'mailman_list_id',
        'enabled',
    ];

    protected $attributes = [
        'enabled' => true,
    ];

    protected static function booted(): void
    {
        static::saved(MailmanListSyncStatus::forget(...));
        static::deleted(MailmanListSyncStatus::forget(...));
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}

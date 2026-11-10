<?php

namespace App\Models;

use App\Support\MailmanListSyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One group assigned to a Mailman list in one role (member or moderator).
 */
class GroupMailmanListGroup extends Model
{
    public const array ROLES = ['member', 'moderator'];

    protected $fillable = [
        'group_mailman_list_id',
        'role',
        'group_dn',
    ];

    protected static function booted(): void
    {
        $forget = function (GroupMailmanListGroup $group): void {
            if ($group->mailmanList) {
                MailmanListSyncStatus::forget($group->mailmanList);
            }
        };
        static::saved($forget);
        static::deleted($forget);
    }

    public function mailmanList(): BelongsTo
    {
        return $this->belongsTo(GroupMailmanList::class, 'group_mailman_list_id');
    }
}

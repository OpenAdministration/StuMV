<?php

namespace App\Models;

use App\Support\MailmanListSyncStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GroupMailmanList extends Model
{
    protected $table = 'group_mailman_lists';

    protected $fillable = [
        'realm',
        'mailman_list_id',
        'enabled',
    ];

    protected $attributes = [
        'enabled' => true,
    ];

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, string>  $members  group DNs whose people are subscribed
     * @param  array<int, string>  $moderators  group DNs whose people moderate the list; empty leaves moderators unmanaged
     */
    public static function createForGroups(array $attributes, array $members, array $moderators = []): self
    {
        $mapping = static::create($attributes);
        $mapping->syncGroups(['member' => $members, 'moderator' => $moderators]);

        return $mapping;
    }

    protected static function booted(): void
    {
        static::saved(MailmanListSyncStatus::forget(...));
        static::deleted(MailmanListSyncStatus::forget(...));
    }

    public function groups(): HasMany
    {
        return $this->hasMany(GroupMailmanListGroup::class);
    }

    /**
     * @return array<int, string>
     */
    public function groupDns(string $role): array
    {
        return $this->groups->where('role', $role)->pluck('group_dn')->values()->all();
    }

    /**
     * Replaces the groups of the given roles; roles not in $groupDnsByRole stay as they are.
     *
     * @param  array<string, array<int, string>>  $groupDnsByRole
     */
    public function syncGroups(array $groupDnsByRole): void
    {
        foreach ($groupDnsByRole as $role => $groupDns) {
            $groupDns = array_values(array_unique($groupDns));
            $this->groups()->where('role', $role)->whereNotIn('group_dn', $groupDns)->get()->each->delete();
            foreach ($groupDns as $groupDn) {
                $this->groups()->firstOrCreate(['role' => $role, 'group_dn' => $groupDn]);
            }
        }

        $this->unsetRelation('groups');
    }

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}

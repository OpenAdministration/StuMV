<?php

namespace App\Support;

use App\Ldap\Group;
use App\Models\GroupMailmanList;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class MailmanListSyncStatus
{
    // Short on purpose: membership changes and edits in Postorius don't invalidate it.
    private const int TTL_SECONDS = 900;

    public function __construct(
        private readonly MailmanClient $mailman,
        private readonly MailmanDesiredMembers $desiredMembers,
    ) {}

    /**
     * @return array{state: 'synced'|'pending'|'stale'|'error', text: string}
     */
    public function for(GroupMailmanList $mapping): array
    {
        return $this->forMany(collect([$mapping]))[$mapping->id];
    }

    /**
     * Cache misses are computed together: one DB/LDAP pass per realm and concurrent Mailman requests.
     *
     * @param  Collection<int, GroupMailmanList>  $mappings
     * @return array<int, array{state: 'synced'|'pending'|'stale'|'error', text: string}> keyed by mapping id
     */
    public function forMany(Collection $mappings): array
    {
        $diffs = [];
        $misses = collect();
        foreach ($mappings as $mapping) {
            $cached = Cache::get(self::cacheKey($mapping->id));
            $cached === null ? $misses->push($mapping) : $diffs[$mapping->id] = $cached;
        }

        foreach ($this->diffMany($misses) as $id => $diff) {
            if ($diff['error'] === null) {
                Cache::put(self::cacheKey($id), $diff, self::TTL_SECONDS);
            }
            $diffs[$id] = $diff;
        }

        return array_map($this->present(...), $diffs);
    }

    public static function forget(GroupMailmanList $mapping): void
    {
        Cache::forget(self::cacheKey($mapping->id));
    }

    public static function forgetList(string $listId): void
    {
        GroupMailmanList::where('mailman_list_id', $listId)->get()->each(self::forget(...));
    }

    public static function forgetRealm(string $realm): void
    {
        GroupMailmanList::where('realm', $realm)->get()->each(self::forget(...));
    }

    private static function cacheKey(int $mappingId): string
    {
        return "mailman-list-status:{$mappingId}";
    }

    /**
     * @param  Collection<int, GroupMailmanList>  $mappings
     * @return array<int, array{error: ?string, pending: int, stale: int}> keyed by mapping id
     */
    private function diffMany(Collection $mappings): array
    {
        if ($mappings->isEmpty()) {
            return [];
        }

        $existingGroupDns = $mappings->pluck('realm')->unique()
            ->flatMap(fn (string $realm) => Group::query()->in(Group::dnRoot($realm))->get()->map(fn (Group $group): string => strtolower((string) $group->getDn())))
            ->flip();

        [$withGroup, $withoutGroup] = $mappings->partition(fn (GroupMailmanList $m): bool => $existingGroupDns->has(strtolower((string) $m->group_dn)));

        $diffs = [];
        foreach ($withoutGroup as $mapping) {
            $diffs[$mapping->id] = ['error' => 'group_missing', 'pending' => 0, 'stale' => 0];
        }

        $rosters = $this->mailman->listMembersMany($withGroup->pluck('mailman_list_id')->unique()->values()->all());
        $desiredByMapping = $this->desiredMembers->forMany($withGroup);

        foreach ($withGroup as $mapping) {
            $roster = $rosters[$mapping->mailman_list_id];
            if ($roster instanceof Throwable) {
                Log::warning('Mailman listMembers failed', ['list_id' => $mapping->mailman_list_id, 'exception' => $roster->getMessage()]);
                $diffs[$mapping->id] = ['error' => 'status_fetch_failed', 'pending' => 0, 'stale' => 0];

                continue;
            }

            $actual = array_column($roster, 'email');
            $desired = array_keys($desiredByMapping[$mapping->id]);
            $diffs[$mapping->id] = [
                'error' => null,
                'pending' => count(array_diff($desired, $actual)),
                'stale' => count(array_diff($actual, $desired)),
            ];
        }

        return $diffs;
    }

    /**
     * @param  array{error: ?string, pending: int, stale: int}  $diff
     * @return array{state: 'synced'|'pending'|'stale'|'error', text: string}
     */
    private function present(array $diff): array
    {
        if ($diff['error'] !== null) {
            return ['state' => 'error', 'text' => __('group_mailman_lists.'.$diff['error'])];
        }

        if ($diff['pending'] === 0 && $diff['stale'] === 0) {
            return ['state' => 'synced', 'text' => __('group_mailman_lists.status_synced')];
        }

        $problems = array_filter([
            $diff['pending'] > 0 ? __('group_mailman_lists.status_pending') : null,
            $diff['stale'] > 0 ? __('group_mailman_lists.status_stale') : null,
        ]);

        // Someone still on the list who no longer should be is worse than someone missing.
        return ['state' => $diff['stale'] > 0 ? 'stale' : 'pending', 'text' => implode(', ', $problems)];
    }
}

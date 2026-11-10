<?php

namespace App\Support;

use App\Models\GroupMailmanList;
use App\Models\GroupMailmanListGroup;
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
        private readonly MailmanRolePlan $plan,
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

        $plans = $this->plan->for($mappings);
        $diffs = [];
        $checkable = collect();
        foreach ($mappings as $mapping) {
            $missingGroups = collect($plans[$mapping->id])->flatMap(fn (array $plan): array => $plan['missing_groups']);
            if ($missingGroups->isNotEmpty() || $mapping->groupDns('member') === []) {
                $diffs[$mapping->id] = ['error' => 'group_missing', 'pending' => 0, 'stale' => 0];
            } else {
                $checkable->push($mapping);
                $diffs[$mapping->id] = ['error' => null, 'pending' => 0, 'stale' => 0];
            }
        }

        foreach (GroupMailmanListGroup::ROLES as $role) {
            $managed = $checkable->filter(fn (GroupMailmanList $m): bool => isset($plans[$m->id][$role]));
            if ($managed->isEmpty()) {
                continue;
            }

            $rosters = $this->mailman->listMembersMany($managed->pluck('mailman_list_id')->unique()->values()->all(), $role);

            foreach ($managed as $mapping) {
                if ($diffs[$mapping->id]['error'] !== null) {
                    continue;
                }

                $roster = $rosters[$mapping->mailman_list_id];
                if ($roster instanceof Throwable) {
                    Log::warning('Mailman roster fetch failed', ['list_id' => $mapping->mailman_list_id, 'role' => $role, 'exception' => $roster->getMessage()]);
                    $diffs[$mapping->id] = ['error' => 'status_fetch_failed', 'pending' => 0, 'stale' => 0];

                    continue;
                }

                // Members keep the exact comparison mailman:sync-lists has always used.
                $normalize = $role === 'member' ? fn (array $emails): array => $emails : fn (array $emails): array => array_map(strtolower(...), $emails);
                $actual = $normalize(array_column($roster, 'email'));
                $desired = $normalize($plans[$mapping->id][$role]['desired']);
                $diffs[$mapping->id]['pending'] += count(array_diff($desired, $actual));
                $diffs[$mapping->id]['stale'] += count(array_diff($actual, $desired));
            }
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

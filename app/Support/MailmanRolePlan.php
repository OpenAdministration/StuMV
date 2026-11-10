<?php

namespace App\Support;

use App\Ldap\Group;
use App\Models\GroupMailmanList;
use App\Models\GroupMailmanListGroup;
use Illuminate\Support\Collection;

/**
 * Who should hold which role on each Mailman list. Members are always managed,
 * moderators only when the list has moderator groups assigned.
 */
class MailmanRolePlan
{
    public function __construct(private readonly MailmanDesiredMembers $desiredMembers) {}

    /**
     * @param  Collection<int, GroupMailmanList>  $mappings
     * @param  (callable(GroupMailmanList, string): void)|null  $warn
     * @return array<int, array<string, array{missing_groups: array<int, string>, desired: array<int, string>}>> mapping id => role => plan
     */
    public function for(Collection $mappings, ?callable $warn = null): array
    {
        if ($mappings->isEmpty()) {
            return [];
        }

        $mappings->each->loadMissing('groups');
        $existingGroupDns = $mappings->pluck('realm')->unique()
            ->flatMap(fn (string $realm) => Group::query()->in(Group::dnRoot($realm))->get()->map(fn (Group $group): string => strtolower((string) $group->getDn())))
            ->flip();

        $plans = [];
        foreach (GroupMailmanListGroup::ROLES as $role) {
            $managed = $mappings->filter(fn (GroupMailmanList $m): bool => $role === 'member' || $m->groupDns($role) !== []);
            $desired = $this->desiredMembers->forMany($managed, $warn, $role);

            foreach ($managed as $mapping) {
                $plans[$mapping->id][$role] = [
                    'missing_groups' => array_values(array_filter($mapping->groupDns($role), fn (string $dn): bool => ! $existingGroupDns->has(strtolower($dn)))),
                    'desired' => array_keys($desired[$mapping->id]),
                ];
            }
        }

        return $plans;
    }
}

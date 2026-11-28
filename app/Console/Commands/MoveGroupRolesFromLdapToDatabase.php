<?php

namespace App\Console\Commands;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMembership;
use Illuminate\Console\Command;

class MoveGroupRolesFromLdapToDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:move-group-roles-from-ldap-to-database
                {community? : Only this realm (short name)}
                {group? : Only this group (cn)}
                {--dry-run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'One-time upgrade from v1: copies the roles linked to each group in LDAP (uniqueMember) into role_group_relation. Must run before ldap:sync-groups, which replaces those members with people.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $realms = Community::query()->get()
            ->reject(fn (Community $realm): bool => $realm->isAdminRealm())
            ->when($this->argument('community') !== null, fn ($realms) => $realms->filter(
                fn (Community $realm): bool => $realm->getShortCode() === $this->argument('community')
            ));

        $created = 0;

        foreach ($realms as $realm) {
            $this->line('> '.$realm->getShortCode());

            $query = Group::query()->in(Group::dnRoot($realm->getShortCode()));

            if ($this->argument('group') !== null) {
                $query->where('cn', '=', $this->argument('group'));
            }

            // roles live below the realm's committees; people in the group are skipped
            $committeesDn = ',ou=Committees,'.$realm->getDn();

            foreach ($query->get() as $group) {
                $this->line('  |-> '.$group->getDn());

                $roleDns = array_filter(
                    $group->getAttribute('uniquemember') ?? [],
                    fn (string $dn): bool => str_ends_with(strtolower($dn), strtolower($committeesDn))
                );

                foreach ($roleDns as $roleDn) {
                    $this->line('  |   |-> '.$roleDn);

                    if ($dryRun) {
                        continue;
                    }

                    $created += (int) GroupMembership::firstOrCreate([
                        'group_dn' => $group->getDn(),
                        'role_dn' => $roleDn,
                    ])->wasRecentlyCreated;
                }
            }
        }

        $this->info($dryRun ? 'Dry run - nothing written.' : "Created $created group/role mapping(s).");

        return self::SUCCESS;
    }
}

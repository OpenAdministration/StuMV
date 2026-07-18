<?php

namespace App\Console\Commands;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Console\Command;

class RenameRole extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:rename-role
                {community : The short name of the community}
                {committee : The ou of the committee the role belongs to}
                {role : The current cn of the role}
                {new-role : The new cn of the role}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renames a role, along with its DB memberships and group relations';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $community = Community::findByUid($this->argument('community'));

        if ($community === null) {
            $this->error('Unknown community: '.$this->argument('community'));

            return self::FAILURE;
        }

        $committee = Committee::findByName($this->argument('community'), $this->argument('committee'));

        if ($committee === null) {
            $this->error('Unknown committee: '.$this->argument('committee'));

            return self::FAILURE;
        }

        $oldCn = $this->argument('role');
        $newCn = $this->argument('new-role');

        if ($oldCn === $newCn) {
            $this->error('The current and new role name are the same.');

            return self::FAILURE;
        }

        $role = $committee->roles()->where('cn', $oldCn)->first();

        if ($role === null) {
            $this->error('Unknown role "'.$oldCn.'" in committee '.$committee->getFirstAttribute('ou'));

            return self::FAILURE;
        }

        if ($committee->roles()->where('cn', $newCn)->exists()) {
            $this->error('A role named "'.$newCn.'" already exists in committee '.$committee->getFirstAttribute('ou'));

            return self::FAILURE;
        }

        $oldDn = $role->getDn();
        $committeeDn = $committee->getDn();

        $this->comment('> '.$oldDn);
        $this->comment('  |-> renaming to cn='.$newCn);

        // Same parent, new RDN - rename($newCn) fills in the "cn=" prefix
        // itself since we're passing a bare attribute value.
        $role->rename($newCn);

        $newDn = $role->getDn();

        // The role's DB-tracked memberships are keyed by (committee_dn,
        // role_cn), not the role's DN - repoint them at the new cn.
        $updatedMemberships = RoleMembership::where('committee_dn', $committeeDn)
            ->where('role_cn', $oldCn)
            ->update(['role_cn' => $newCn]);

        // Group-role relations reference the role's own DN directly, which
        // just changed.
        $updatedGroupRelations = GroupMembership::where('role_dn', $oldDn)
            ->update(['role_dn' => $newDn]);

        $this->comment("  |-> updated $updatedMemberships role membership(s)");
        $this->comment("  |-> updated $updatedGroupRelations group relation(s)");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Console\Command;

class MoveRoleToCommittee extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:move-role-to-committee
                {community : The short name of the community}
                {committee : The ou of the committee the role currently belongs to}
                {role : The cn of the role to move}
                {target-committee : The ou of the destination committee}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Moves a role to another committee, along with its DB memberships and group relations';

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

        $sourceCommittee = Committee::findByName($this->argument('community'), $this->argument('committee'));

        if ($sourceCommittee === null) {
            $this->error('Unknown committee: '.$this->argument('committee'));

            return self::FAILURE;
        }

        $targetCommittee = Committee::findByName($this->argument('community'), $this->argument('target-committee'));

        if ($targetCommittee === null) {
            $this->error('Unknown target committee: '.$this->argument('target-committee'));

            return self::FAILURE;
        }

        if ($sourceCommittee->getDn() === $targetCommittee->getDn()) {
            $this->error('The source and target committee are the same.');

            return self::FAILURE;
        }

        $role = $sourceCommittee->roles()->where('cn', $this->argument('role'))->first();

        if ($role === null) {
            $this->error('Unknown role "'.$this->argument('role').'" in committee '.$sourceCommittee->getFirstAttribute('ou'));

            return self::FAILURE;
        }

        $oldDn = $role->getDn();
        $oldCommitteeDn = $sourceCommittee->getDn();

        $this->comment('> '.$oldDn);
        $this->comment('  |-> moving to '.$targetCommittee->getDn());

        // Relocates the LDAP entry itself: same RDN (cn), new parent.
        $role->move($targetCommittee->getDn());

        $newDn = $role->getDn();

        // The role's DB-tracked memberships are keyed by (committee_dn,
        // role_cn), not the role's DN - repoint them at the new committee.
        $updatedMemberships = RoleMembership::where('role_cn', $role->getFirstAttribute('cn'))
            ->where('committee_dn', $oldCommitteeDn)
            ->update(['committee_dn' => $targetCommittee->getDn()]);

        // Group-role relations reference the role's own DN directly, which
        // just changed.
        $updatedGroupRelations = GroupMembership::where('role_dn', $oldDn)
            ->update(['role_dn' => $newDn]);

        $this->comment("  |-> updated $updatedMemberships role membership(s)");
        $this->comment("  |-> updated $updatedGroupRelations group relation(s)");

        return self::SUCCESS;
    }
}

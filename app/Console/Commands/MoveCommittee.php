<?php

namespace App\Console\Commands;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Illuminate\Console\Command;

class MoveCommittee extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:move-committee
                {community : The short name of the community}
                {committee : The ou of the committee to move}
                {target-committee? : The ou of the destination parent committee; omit to move it to the top level}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Moves a committee (and all its descendants' roles, DB memberships and group relations) under another parent committee, or to the top level";

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

        $targetArg = $this->argument('target-committee');

        if ($targetArg !== null) {
            $targetCommittee = Committee::findByName($this->argument('community'), $targetArg);

            if ($targetCommittee === null) {
                $this->error('Unknown target committee: '.$targetArg);

                return self::FAILURE;
            }

            $newParentDn = $targetCommittee->getDn();

            if ($newParentDn === $committee->getDn() || str_ends_with($newParentDn, ','.$committee->getDn())) {
                $this->error('Cannot move a committee into itself or one of its own descendants.');

                return self::FAILURE;
            }
        } else {
            $newParentDn = Committee::dnRoot($this->argument('community'));
        }

        if ($newParentDn === $committee->getParentDn()) {
            $this->error('The committee is already directly under that parent.');

            return self::FAILURE;
        }

        $oldCommitteeDn = $committee->getDn();

        // Gather every role under this committee and its descendants before
        // moving anything - their DNs (and the committee_dn on their DB
        // memberships) all shift once the top entry relocates, since a
        // descendant's DN is always its own RDN chain plus the parent's DN.
        $affectedCommittees = collect([$committee])->concat($committee->descendants()->get());

        $roleUpdates = [];
        foreach ($affectedCommittees as $affectedCommittee) {
            foreach ($affectedCommittee->roles()->get() as $role) {
                $roleUpdates[] = [
                    'role_cn' => $role->getFirstAttribute('cn'),
                    'old_committee_dn' => $affectedCommittee->getDn(),
                    'old_role_dn' => $role->getDn(),
                ];
            }
        }

        $this->comment('> '.$oldCommitteeDn);
        $this->comment('  |-> moving to '.$newParentDn);

        // Relocates the whole subtree in one LDAP operation - descendant
        // entries keep their relative position; only their effective DN
        // (computed from the new parent) changes.
        $committee->move($newParentDn);

        $newCommitteeDn = $committee->getDn();

        $updatedMemberships = 0;
        $updatedGroupRelations = 0;

        foreach ($roleUpdates as $update) {
            $newRoleCommitteeDn = $this->relocateDn($update['old_committee_dn'], $oldCommitteeDn, $newCommitteeDn);
            $newRoleDn = $this->relocateDn($update['old_role_dn'], $oldCommitteeDn, $newCommitteeDn);

            $updatedMemberships += RoleMembership::where('committee_dn', $update['old_committee_dn'])
                ->where('role_cn', $update['role_cn'])
                ->update(['committee_dn' => $newRoleCommitteeDn]);

            $updatedGroupRelations += GroupMembership::where('role_dn', $update['old_role_dn'])
                ->update(['role_dn' => $newRoleDn]);
        }

        $this->comment("  |-> updated $updatedMemberships role membership(s)");
        $this->comment("  |-> updated $updatedGroupRelations group relation(s)");

        return self::SUCCESS;
    }

    /**
     * Rewrites a DN that lives under the moved committee's OLD dn so it
     * points at the equivalent location under its NEW dn, by swapping the
     * trailing part of the DN that belongs to the moved committee itself.
     */
    private function relocateDn(string $dn, string $oldCommitteeDn, string $newCommitteeDn): string
    {
        if ($dn === $oldCommitteeDn) {
            return $newCommitteeDn;
        }

        return substr($dn, 0, -strlen(','.$oldCommitteeDn)).','.$newCommitteeDn;
    }
}

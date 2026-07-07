<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Flux\Flux;
use Livewire\Component;

class CommitteeTreeItem extends Component
{
    public string $realm_uid;
    public string $dn;
    public bool $unfolded = false;
    public bool $isLastItem = false;

    public string $deleteConfirmText = "";

    public function render()
    {
        $community = Community::findByUid($this->realm_uid);
        $committee = Committee::findOrFail($this->dn);
        $children = $committee->descendants()->orderBy('description')->get();

        return view('livewire.committee.committee-tree-item', [
            'community' => $community,
            'committee' => $committee,
            'children' => $children ?? [],
            'hasChildren' => count($children) > 0 ? true : false,
        ]);
    }

    public function getChildren()
    {
        $this->unfolded = true;
    }

    public function deleteCommittee(string $dn, string $cn)
    {
        $this->authorize('delete', [$c, $community]);

        $community = Community::findByUid($this->realm_uid);
        $c = Committee::findOrFail($dn);

        if ($this->deleteConfirmText !== $c->getFirstAttribute('ou')){
            $this->addError('deleteConfirmText', __('Does not equal :text', [ 'text' => $c->getFirstAttribute('ou') ]));
            return;
        }

        // Delete role memberships and role group relashionships for this committee and its descendants
        $dn = $c->getDn();
        $roles = $c->roles()->get();
        foreach ($roles as $role) {
            RoleMembership::where('role_cn', $role->getFirstAttribute('cn'))
                ->where('committee_dn', $dn)
                ->delete();
            GroupMembership::where('role_dn', $role->getDn())
                ->delete();
        }

        $descendants = $c->descendants()->get();
        foreach ($descendants as $descendant) {
            $roles = $descendant->roles()->get();
            foreach ($roles as $role) {
                RoleMembership::where('role_cn', $role->getFirstAttribute('cn'))
                    ->where('committee_dn', $descendant->getDn())
                    ->delete();
                GroupMembership::where('role_dn', $role->getDn())
                    ->delete();
            }
        }

        // Delete the committee and its descendants recursively
        $c->delete(recursive: true);

        Flux::modal('delete-committee-' . $cn)->close();
        return redirect()->back();
    }
}

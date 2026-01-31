<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use Livewire\Component;

class CommitteeTreeItem extends Component
{
    public string $realm_uid;
    public string $dn;
    public bool $unfolded = false;
    public bool $isLastItem = false;

    public function render()
    {
        $community = Community::findByUid($this->realm_uid);
        $committee = Committee::findOrFail($this->dn);
        $children = $committee->descendants()->orderBy('cn')->get();

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

    public function deletePrepare(string $dn): void
    {
        $community = Community::findByUid($this->realm_uid);
        $c = Committee::findOrFail($dn);
        $this->authorize('delete', [$c, $community]);
        $this->deleteCommitteeDn = $dn;
        $this->deleteCommitteeName = $c->getFirstAttribute('description');
        $this->deleteCommitteeOu = $c->getFirstAttribute('ou');
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $community = Community::findByUid($this->realm_uid);
        $c = Committee::findOrFail($this->deleteCommitteeDn);
        $this->authorize('delete', [$c, $community]);

        if ($this->deleteConfirmText !== $c->getFirstAttribute('ou')){
            $this->addError('deleteConfirmText', __('Does not equal :text', $c->getFirstAttribute('ou')));
            return;
        }
        $c->delete(recursive: true);

        $this->close();
    }
}

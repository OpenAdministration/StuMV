<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
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

    public function deleteCommittee(string $dn, string $cn)
    {
        $community = Community::findByUid($this->realm_uid);
        $c = Committee::findOrFail($dn);

        if ($this->deleteConfirmText !== $c->getFirstAttribute('ou')){
            $this->addError('deleteConfirmText', __('Does not equal :text', $c->getFirstAttribute('ou')));
            return;
        }

        $this->authorize('delete', [$c, $community]);
        $c->delete(recursive: true);

        Flux::modal('delete-committee-' . $cn)->close();
    }
}

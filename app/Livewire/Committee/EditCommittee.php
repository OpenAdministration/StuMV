<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Rules\UniqueCommittee;
use LdapRecord\Models\Attributes\DistinguishedName;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class EditCommittee extends Component
{

    #[Locked]
    public string $realm_uid;

    #[Locked]
    public string $ou = "";

    #[Locked]
    public string $parent_ou = "";

    #[Validate('required|min:3')]
    public string $description = "";

    public function mount(Community $uid, string $ou){
        $this->realm_uid = $uid->getFirstAttribute('ou');
        $this->ou = $ou;
        $c = Committee::findByName($this->realm_uid, $ou);
        $this->description = $c->getFirstAttribute('description');
        $parentRdn = DistinguishedName::explode($c->getParentDn())[0];
        $this->parent_ou = explode("=",$parentRdn, 2)[1];
        if($this->parent_ou === 'Committees'){
            $this->parent_ou = '';
        }
    }

    public function render()
    {
        $parents = Committee::fromCommunity($this->realm_uid)
            ->whereNotEquals('ou', 'Committees') // remove parent Folder from Results;
            ->get()
        ;
        return view('livewire.committee.edit-committee', [
            'select_parents' => $parents,
        ]);
    }

    public function save(){

        $this->validate();

        $c = Committee::findByName($this->realm_uid, $this->ou);
        $c->setAttribute('description', $this->description);
        $c->save();
        return response()
            ->redirectToRoute('committees.list', ['uid' => $this->realm_uid])
            ->with('message', __('Saved'));
    }
}

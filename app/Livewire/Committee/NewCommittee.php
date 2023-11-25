<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Rules\UniqueCommittee;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class NewCommittee extends Component
{

    #[Locked]
    public string $realm_uid;

    public string $parent_ou = "";

    #[Validate]
    public string $ou = "";

    public string $description = "";

    public function mount(Community $uid){
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function rules(): array
    {
        return [
            'ou' => new UniqueCommittee($this->realm_uid)
        ];
    }

    public function render()
    {
        $parents = Committee::fromCommunity($this->realm_uid)
            ->whereNotEquals('ou', 'Committees') // remove parent Folder from Results;
            ->get()
        ;
        return view('livewire.committee.new-committee', [
            'select_parents' => $parents,
        ]);
    }

    public function save(){

        $this->validate();

        $dn = Committee::dnFrom($this->realm_uid, $this->ou, $this->parent_ou);
        $c = new Committee([
            'ou' => $this->ou,
            'description' => $this->description
        ]);
        $c->setDn($dn);
        $c->save();
        return response()->redirectToRoute('committees.list', ['uid' => $this->realm_uid]);
    }
}

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

    public string $parent_dn = "";

    #[Validate]
    public string $ou = "";

    #[Validate('required|min:3')]
    public string $description = "";

    public function mount(Community $uid){
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function rules(): array
    {
        return [
            'ou' => [
                'alpha_dash',
                new UniqueCommittee($this->realm_uid)
            ]
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
        ])->title(__('committees.new_title'));
    }

    public function save(){

        $this->validate();

        $dn = Committee::dnFrom($this->realm_uid, $this->ou, parentDn: $this->parent_dn);
        $c = new Committee([
            'ou' => $this->ou,
            'description' => $this->description
        ]);
        $c->setDn($dn);
        $c->save();
        return response()->redirectToRoute('committees.list', ['uid' => $this->realm_uid]);
    }
}

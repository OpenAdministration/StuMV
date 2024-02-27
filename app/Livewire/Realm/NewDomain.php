<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\Domain;
use App\Rules\UniqueDomain;
use dacoto\DomainValidator\Validator\Domain as DomainValidator;
use Illuminate\Validation\Rules\Unique;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class NewDomain extends Component
{
    #[Locked]
    public string $uid;

    #[Validate(as: 'Domain')]
    public string $dc;

    public function mount(Community $uid){
        $this->uid = $uid->getFirstAttribute('ou');
    }
    public function rules(){
        return [
            'dc' => [
                new DomainValidator(),
                new UniqueDomain(),
            ],

        ];
    }
    public function render()
    {
        return view('livewire.realm.new-domain')->title(__('realms.new_domain_title', ['realm' => $this->uid]));
    }

    public function save(){
        $this->validate();

        $d = Domain::make([
            'dc' => $this->dc,
        ]);

        $d->setDn("dc=$this->dc," . Domain::dnRoot($this->uid));
        $d->save();
        $this->redirectRoute('realms.domains', ['uid' => $this->uid]);
    }


}

<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\Domain;
use App\Rules\UniqueDomain;
use dacoto\DomainValidator\Validator\Domain as DomainValidator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class NewDomain extends Component
{
    #[Locked]
    public string $uid;

    #[Validate(as: 'Domain')]
    public string $dc;

    public function mount(Community $realm)
    {
        $this->authorize('create', [Domain::class, $realm]);
        $this->uid = $realm->getFirstAttribute('ou');
    }

    #[Computed]
    public function community(): Community
    {
        return Community::findOrFailByUid($this->uid);
    }

    public function rules()
    {
        return [
            'dc' => [
                new DomainValidator,
                new UniqueDomain,
            ],

        ];
    }

    public function render()
    {
        return view('livewire.realm.new-domain')->title(__('realms.new_domain_title', ['realm' => $this->uid]));
    }

    public function save()
    {
        $this->authorize('create', [Domain::class, $this->community()]);
        $this->validate();

        $d = Domain::make([
            'dc' => $this->dc,
        ]);

        $d->setDn("dc=$this->dc,".Domain::dnRoot($this->uid));
        $d->save();
        $this->redirectRoute('realms.domains', ['realm' => $this->uid]);
    }
}

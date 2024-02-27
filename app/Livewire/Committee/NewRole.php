<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Rules\UniqueRole;
use Livewire\Attributes\Locked;
use Livewire\Component;

class NewRole extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    public string $cn;

    public string $description;

    public function mount(Community $uid, $ou){
        $this->uid = $uid->getFirstAttribute('ou');
        $this->ou = $ou;
    }

    public function rules(){
        return [
            'cn' => new UniqueRole($this->uid, $this->ou),
        ];
    }

    public function render(): \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\View\View|\Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.committee.new-role')->title(__('committees.new_role_title', ['committee' => $this->ou]));
    }

    public function updated(): void
    {
        $this->validate();
    }
    public function save(){
        $this->validate();
        $c = Committee::fromCommunity($this->uid)->findByOrFail('ou', $this->ou);
        $r = new Role([
            'cn' => $this->cn,
            'description' => $this->description,
            'uniqueMember' => '',
        ]);
        $r->inside($c);
        $r->save();

        return redirect()->route('committees.roles', ['ou' => $this->ou, 'uid' => $this->uid])
            ->with('message', __('New Role created'));
    }
}

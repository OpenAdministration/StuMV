<?php

namespace App\Livewire\Committee;

use App\Ldap\Community;
use App\Ldap\User;
use Livewire\Attributes\Locked;
use Livewire\Component;

class AddUserToRole extends Component
{

    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    #[Locked]
    public string $cn;

    public string $user_dn;

    public function mount(Community $uid, $ou, $cn){
        $this->uid = $uid->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
    }

    public function render()
    {
        $c = Community::findByOrFail('ou', $this->uid);
        $users = $c->membersGroup()->members();

        return view('livewire.committee.add-user-to-role', ['users' => $users]);
    }
}

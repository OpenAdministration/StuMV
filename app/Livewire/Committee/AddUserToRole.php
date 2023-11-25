<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\User;
use App\Models\Role;
use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Symfony\Contracts\Service\Attribute\Required;

class AddUserToRole extends Component
{

    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    #[Locked]
    public string $cn;

    #[Required]
    public string $username;

    public string $start_date;
    public string $end_date;
    public string $decision_date;

    public string $comment;

    public function mount(Community $uid, $ou, $cn){
        $this->uid = $uid->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
        $this->start_date = today()->format('Y-m-d');
    }

    public function render()
    {
        $c = Community::findByOrFail('ou', $this->uid);
        $users = $c->membersGroup()->members()->get();

        return view('livewire.committee.add-user-to-role', ['users' => $users]);
    }

    public function save(){
        $committee = Committee::findByName($this->uid, $this->ou);
        $user = User::findOrFailByUsername($this->username);

        Role::create([
            'role_dn' => $this->cn,
            'committee_dn' => $committee->getDn(),
            'username' => $this->username,
            'from' => $this->start_date,
            'until' => $this->end_date,
            'decided' => $this->decision_date,
            'comment' => $this->comment,
        ]);
    }

}

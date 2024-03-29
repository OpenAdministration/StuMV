<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\RoleMembership;
use App\Rules\UserIsMember;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AddUserToRole extends Component
{

    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    #[Locked]
    public string $cn;

    #[Validate]
    public string $username = '';

    #[Validate('date:Y-m-d')]
    public $start_date;
    #[Validate('date:Y-m-d')]
    public $end_date = '';
    #[Validate('date:Y-m-d')]
    public $decision_date = '';

    #[Validate('string')]
    public string $comment = '';

    public function mount(Community $uid, $ou, $cn){
        $this->uid = $uid->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
        $this->start_date = today()->format('Y-m-d');
    }

    public function rules(){
        return [
            'username' => [
                'required',
                new UserIsMember($this->uid)
            ]
        ];
    }

    public function render()
    {
        $c = Community::findByOrFail('ou', $this->uid);
        $users = $c->membersGroup()->members()->get();

        return view('livewire.committee.add-user-to-role', ['users' => $users])
            ->title(__('realms.add_members_to_role_title', ['role' => $this->cn]));
    }

    public function save(){
        $this->validate();

        $committee = Committee::findByName($this->uid, $this->ou);
        RoleMembership::create([
            'role_cn' => $this->cn,
            'committee_dn' => $committee->getDn(),
            'username' => $this->username,
            'from' => $this->start_date,
            'until' => !empty($this->end_date) ? $this->end_date : null,
            'decided' => !empty($this->decision_date) ? $this->decision_date : null,
            'comment' => !empty($this->comment) ? $this->comment : null,
        ]);
        return redirect()->route('committees.roles.members', [
            'uid' => $this->uid,
            'ou' => $this->ou,
            'cn' => $this->cn,
        ])->with('message', __('roles.added_user', ['username' => $this->username, 'role' => $this->cn]));
    }

}

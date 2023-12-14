<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\User;
use App\Models\Role;
use App\Models\RoleMembership;
use App\Rules\UserIsMember;
use Carbon\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Symfony\Contracts\Service\Attribute\Required;

class EditRoleMembership extends Component
{

    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    #[Locked]
    public string $cn;

    #[Locked]
    public int $id;

    #[Locked]
    public string $username = '';

    #[Validate('required|date:Y-m-d', as: 'Starting Date')]
    public $start_date;
    #[Validate('nullable|date:Y-m-d', as: 'Ending Date')]
    public $end_date = '';
    #[Validate('nullable|date:Y-m-d', as: 'Decision Date')]
    public $decision_date = '';

    #[Validate('string')]
    public string $comment = '';

    public function mount(Community $uid, $ou, $cn, $id){
        $this->uid = $uid->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
        $this->id = $id;
        $membership = RoleMembership::findOrFail($id);
        $this->username = $membership->username;
        $this->start_date = $membership->from?->format('Y-m-d');
        $this->end_date = $membership->until?->format('Y-m-d');
        $this->decision_date = $membership->decided?->format('Y-m-d');
        $this->comment = $membership->comment ?? '';
    }

    public function render()
    {
        return view('livewire.committee.edit-role-membership');
    }

    public function save(){
        $this->validate();
        $membership = RoleMembership::findOrFail($this->id);
        $membership->update([
            'from' => $this->start_date,
            'until' => !empty($this->end_date) ? $this->end_date : null,
            'decided' => !empty($this->decision_date) ? $this->decision_date : null,
            'comment' => !empty($this->comment) ? $this->comment : null,
        ]);
        return redirect()->route('committees.roles.members', [
            'uid' => $this->uid,
            'ou' => $this->ou,
            'cn' => $this->cn,
            'id' => $this->id,
        ])->with('message', __('roles.edit_member_success', ['username' => $this->username, 'role' => $this->cn]));
    }

}

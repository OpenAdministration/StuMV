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

class EditRole extends Component
{

    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    #[Locked]
    public string $cn;

    #[Validate('string|required|min:1')]
    public string $description;

    public function mount(Community $uid, $ou, $cn){
        $this->uid = $uid->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
        $committe = Committee::findByNameOrFail($uid, $ou);
        $role = $committe->roles()->where('cn', $cn)->first();
        $this->description = $role->getFirstAttribute('description');
    }

    public function render()
    {
        return view('livewire.committee.edit-role')->title(__('roles.edit_title'));
    }

    public function save(){
        $this->validate();
        $committe = Committee::findByNameOrFail($this->uid, $this->ou);
        $role = $committe->roles()->where('cn', $this->cn)->first();
        $role->save([
           'description' => $this->description
        ]);
        return redirect()->route('committees.roles', [
            'uid' => $this->uid,
            'ou' => $this->ou,
            'cn' => $this->cn,
        ])->with('message', __('roles.edit_success', ['role' => $this->cn]));
    }

}

<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

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

    public function mount(Community $uid, $ou, $cn)
    {
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

    public function save()
    {
        $this->validate();
        $committe = Committee::findByNameOrFail($this->uid, $this->ou);
        $role = $committe->roles()->where('cn', $this->cn)->first();
        $role->save([
            'description' => $this->description,
        ]);

        Flux::toast(variant: 'success', text: __('roles.edit_success', ['role' => $this->cn]));

        return to_route('committees.roles', [
            'uid' => $this->uid,
            'ou' => $this->ou,
            'cn' => $this->cn,
        ]);
    }
}

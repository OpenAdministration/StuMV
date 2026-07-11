<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use App\Ldap\Group;
use Flux\Flux;
use Livewire\Attributes\Rule;
use Livewire\Component;

class EditGroup extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $dn;

    #[Locked]
    public string $cn;

    #[Rule('required|min:6')]
    public string $name;

    public function mount(Community $uid, $cn)
    {
        $this->uid = $uid->getFirstAttribute('ou');
        $this->cn = $cn;
        $this->dn = Group::dnFrom($this->uid, $cn);
        $group = Group::findOrFail($this->dn);
        $this->name = $group->getFirstAttribute('description');
    }

    public function render()
    {
        return view('livewire.group.edit-group');
    }

    public function save()
    {
        $this->validate();
        try {
            $group = Group::findOrFail($this->dn);
            $group->save([
                'description' => $this->name,
            ]);

            Flux::toast(variant: 'success', text: __('groups.edit_success'));

            return to_route('realms.groups.roles', ['uid' => $this->uid, 'cn' => $this->cn]);
        } catch (LdapRecordException $exception) {
            $this->addError('cn', $exception->getMessage());

            return false;
        }
    }
}

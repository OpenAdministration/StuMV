<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\OpenLDAP\Group;
use Livewire\Attributes\Rule;
use Livewire\Component;

class NewGroup extends Component
{
    #[Rule('required|string|min:2|alpha_dash')]
    public string $cn = "";

    #[Rule('required|string|min:2|alpha_dash')]
    public string $realm_uid = "";

    #[Rule('required|min:6')]
    public string $name = "";

    public function mount(Community $uid){
        $this->realm_uid = $uid->getShortCode();
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.group.new-group')->title(__('groups.new_title'));
    }

    public function save()
    {
        $this->validate();
        try{
            $group = new Group([
                'cn' => $this->cn,
                'description' => $this->name,
                'uniqueMember' => '',
            ]);
            $group->setDn("cn=$this->cn,ou=Groups,ou=$this->realm_uid,ou=Communities,{$group->getBaseDn()}");
            $group->save();
            return redirect()->route('realms.groups.roles', ['uid' => $this->realm_uid, 'cn' => $this->cn])
                ->with('message', __('Added new Group'));
        } catch (LdapRecordException $exception){
            $this->addError('cn', $exception->getMessage());
            return false;
        }
    }


}

<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

class NewAdmin extends Component
{
    #[Rule('required|string')]
    public string $dn = "";

    #[Rule('required|string')]
    public string $realm_uid = "";


    public function mount(Community $uid):void{
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        $community = Community::findOrFailByUid($this->realm_uid);
        $userList =  $community->membersGroup()->members()->get();
        $admins = $community->adminsGroup()->members()->get();

        return view('livewire.realm.new-admin', [
            'selectable_users' => $userList->except($admins->keys()),
            'community' => $community,
        ])->title(__('realms.admins_new_title', ['realm' => $this->realm_uid]));
    }

    public function save()
    {
        $this->validate();
        try{
            $user = User::findOrFail($this->dn);
            $realm = Community::findOrFailByUid($this->realm_uid);
            $realm->adminsGroup()->members()->attach($user);
            return redirect()->route('realms.admins', ['uid' => $this->realm_uid])
                ->with('message', __('Added new Admin'));
        } catch (LdapRecordException $exception){
            $this->addError('dn', $exception->getMessage());
            return false;
        }
    }


}

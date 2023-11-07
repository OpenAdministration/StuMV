<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Rule;
use Livewire\Component;

class NewModerator extends Component
{
    public string $search = "";
    #[Rule('required|string')]
    public string $dn = "";

    #[Rule('required|string')]
    public string $realm_uid = "";


    public function mount($uid):void{
        $this->realm_uid = $uid;
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        $userList = User::query()
            ->search('uid', $this->search)
            ->search('dn', $this->search)
            ->get();
        return view('livewire.realm.new-moderator', ['selectable_users' => $userList]);
    }

    public function save()
    {
        $this->validate();
        try{
            $user = User::findOrFail($this->dn);
            $realm = Community::findOrFailByUid($this->realm_uid);
            $realm->moderatorsGroup()->first()?->members()->attach($user);
            return redirect()->route('realms.mods', ['uid' => $this->realm_uid])
                ->with('message', __('Added new Moderator'));
        } catch (LdapRecordException $exception){
            $this->addError('dn', $exception->getMessage());
            return false;
        }
    }


}

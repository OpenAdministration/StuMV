<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use LdapRecord\LdapRecordException;
use LdapRecord\Query\Builder;
use Livewire\Attributes\Rule;
use Livewire\Component;

class NewMember extends Component
{
    public string $search = "";
    #[Rule('required|string')]
    public string $dn = "";

    #[Rule('required|string')]
    public string $realm_uid = "";


    public function mount(Community $uid):void{
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        $userList = User::query()
            ->search('uid', $this->search)
            ->search('dn', $this->search)
            ->get();
        return view('livewire.realm.new-member', ['selectable_users' => $userList])
            ->title(__('realms.new_member_title', ['realm' => $this->realm_uid]));
    }

    public function save()
    {
        $this->validate();
        try {
            $user = User::findOrFail($this->dn);
            $realm = Community::findOrFailByUid($this->realm_uid);
            $realm->membersGroup()->members()->attach($user);
            return redirect()->route('realms.members', ['uid' => $this->realm_uid])
                ->with('message', __('Added new Member'));
        } catch (LdapRecordException $exception) {
            $this->addError('dn', $exception->getMessage());
            return false;
        }
    }


}

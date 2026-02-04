<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Flux\Flux;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

class NewModerator extends Component
{
    #[Rule('required')]
    public array $dn = [];

    #[Rule('required|string')]
    public string $realm_uid = "";

    public function mount(Community $uid) : void
    {
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        $c = Community::findOrFailByUid($this->realm_uid);
        $userList = $c->membersGroup()->members()->get();
        $moderators = $c->moderatorsGroup()->members()->get();
        // baseCollection does like strings in contains, ldapCollection does not...
        $moderatorDns = $moderators->modelDns()->toBase();
        $selectable_users = $userList->filter(fn ($user) => $moderatorDns->doesntContain($user->getDn()));
        return view('livewire.realm.new-moderator', [
            'community' => $c,
            'selectable_users' => $selectable_users,
        ]);
    }

    public function save()
    {
        $this->validate();
        foreach ($this->dn as $dn) {
            try {
                $user = User::findOrFail($dn);
                $realm = Community::findOrFailByUid($this->realm_uid);
                $realm->moderatorsGroup()->members()->attach($user);

                Flux::toast(variant: 'success', text: __('Added new Moderator'));
            } catch (LdapRecordException $exception) {
                $this->addError('dn', $exception->getMessage());
                return false;
            }
        }
        return redirect()->route('realms.mods', ['uid' => $this->realm_uid]);
    }
}

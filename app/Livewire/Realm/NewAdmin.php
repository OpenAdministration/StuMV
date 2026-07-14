<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Flux\Flux;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Rule;
use Livewire\Component;

class NewAdmin extends Component
{
    #[Rule('required|array')]
    public array $dn = [];

    #[Rule('required|string')]
    public string $realm_uid = '';

    public function mount(Community $realm)
    {
        $this->realm_uid = $realm->getFirstAttribute('ou');
    }

    public function render()
    {
        $community = Community::findOrFailByUid($this->realm_uid);
        $userList = $community->membersGroup()->members()->get();
        $admins = $community->adminsGroup()->members()->get();
        $adminDns = $admins->modelDns()->toBase();
        $selectable_users = $userList->filter(fn ($user) => $adminDns->doesntContain($user->getDn()));

        return view('livewire.realm.new-admin', [
            'selectable_users' => $selectable_users,
            'community' => $community,
        ])->title(__('realms.admins_new_title', ['realm' => $this->realm_uid]));
    }

    public function save()
    {
        $this->validate();
        foreach ($this->dn as $dn) {
            try {
                $user = User::findOrFail($dn);
                $realm = Community::findOrFailByUid($this->realm_uid);
                $realm->adminsGroup()->members()->attach($user);

                Flux::toast(variant: 'success', text: __('Added new Admin'));
            } catch (LdapRecordException $exception) {
                Flux::toast(variant: 'danger', text: $exception->getMessage());

                return false;
            }
        }

        return to_route('realms.admins', ['realm' => $this->realm_uid]);
    }
}

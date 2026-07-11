<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Flux\Flux;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Rule;
use Livewire\Component;

class NewMember extends Component
{
    public string $search = '';

    #[Rule('required|array')]
    public array $selectedUsers = [];

    #[Rule('required|string')]
    public string $realm_uid = '';

    public function mount(Community $uid): void
    {
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function render(): Factory|View|Application
    {
        $userList = User::query()->search()
            ->get();

        return view('livewire.realm.new-member', ['selectable_users' => $userList])
            ->title(__('realms.new_member_title', ['realm' => $this->realm_uid]));
    }

    public function save()
    {
        $this->validate();
        foreach ($this->selectedUsers as $dn) {
            try {
                $user = User::findOrFail($dn);
                $realm = Community::findOrFailByUid($this->realm_uid);
                $realm->membersGroup()->members()->attach($user);

                \App\Models\User::where('username', $user->getFirstAttribute('uid'))->update(['realm' => $this->realm_uid]);
            } catch (LdapRecordException $exception) {
                $this->addError('dn', $exception->getMessage());

                return false;
            }
        }

        Flux::toast(variant: 'success', text: __('Added new Member'));

        return to_route('realms.members', ['uid' => $this->realm_uid]);
    }
}

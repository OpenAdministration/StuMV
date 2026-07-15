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

    public function mount(Community $realm): void
    {
        $this->realm_uid = $realm->getFirstAttribute('ou');
    }

    public function render(): Factory|View|Application
    {
        $realm = Community::findOrFailByUid($this->realm_uid);
        $userList = User::query()->search()
            ->get();
        $memberDns = $realm->membersGroup()->members()->get()->modelDns()->toBase();
        $selectable_users = $userList->filter(fn ($user) => $memberDns->doesntContain($user->getDn()));

        return view('livewire.realm.new-member', ['selectable_users' => $selectable_users])
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

        Flux::toast(variant: 'success', text: __('realms.added_new_member'));

        return to_route('realms.members', ['realm' => $this->realm_uid]);
    }
}

<?php

namespace App\Livewire;

use App\Ldap\SuperUserGroup;
use App\Ldap\User;
use Flux\Flux;
use LdapRecord\LdapRecordException;
use Livewire\Component;

class AddSuperAdmins extends Component
{
    public array $usersToAdd = [];

    public function render()
    {
        $users = User::get();
        $adminDns = SuperUserGroup::group()->members()->get()->modelDns()->toBase();
        $selectableUsers = $users->filter(fn ($user) => $adminDns->doesntContain($user->getDn()));

        return view('livewire.add-super-admins', [
            'users' => $selectableUsers,
        ])->title(__('superadmins.new_title'));
    }

    public function save()
    {
        foreach ($this->usersToAdd as $u) {
            try {
                $user = User::findOrFail($u);
                SuperUserGroup::group()->members()->attach($user);
                Flux::toast(variant: 'success', text: __('Added new Superadmin'));

                return to_route('superadmins.list');
            } catch (LdapRecordException $exception) {
                $this->addError('dn', $exception->getMessage());

                return false;
            }
        }
    }
}

<?php

namespace App\Livewire;

use App\Ldap\SuperUserGroup;
use App\Ldap\User;
use Flux\Flux;
use Livewire\Component;

class AddSuperAdmins extends Component
{
    public array $usersToAdd = [];

    public function render()
    {
        $users = User::orderBy('cn')->get();

        return view('livewire.add-super-admins', [
            'users' => $users,
        ])->title(__('superadmins.new_title'));
    }

    public function save()
    {
        foreach ($usersToAdd as $u) {
            try {
                $user = User::findOrFail($u->get);
                SuperUserGroup::attach($user);
                Flux::toast(variant: 'success', text: __('Added new Superadmin'));
                return redirect()->route('superadmins.list');
            } catch (LdapRecordException $exception) {
                $this->addError('dn', $exception->getMessage());
                return false;
            }
        }
    }
}

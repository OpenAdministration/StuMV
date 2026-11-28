<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

class EditGroup extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $dn;

    #[Locked]
    public string $cn;

    #[Rule('required|min:6')]
    public string $name;

    public function mount(Community $realm, string $cn): void
    {
        $this->uid = $realm->getFirstAttribute('ou');
        $this->cn = $cn;
        $this->dn = Group::dnFrom($this->uid, $cn);
        $group = Group::findOrFail($this->dn);
        $this->name = $group->getFirstAttribute('description');
    }

    public function render(): Factory|View
    {
        return view('livewire.group.edit-group');
    }

    /** @return false|null */
    public function save()
    {
        $this->validate();
        try {
            $group = Group::findOrFail($this->dn);
            $before = $group->getFirstAttribute('description');
            $group->save([
                'description' => $this->name,
            ]);
            resolve(ActivityLogger::class)->recordDescriptionChange('group.updated', $this->uid, $group, $before);

            Flux::toast(variant: 'success', text: __('groups.edit_success'));

            $this->redirect(url()->previous());

            return null;
        } catch (LdapRecordException $exception) {
            $this->addError('cn', $exception->getMessage());

            return false;
        }
    }
}

<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class EditRole extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    #[Locked]
    public string $cn;

    #[Validate('string|required|min:1')]
    public string $description;

    public function mount(Community $realm, string $ou, string $cn): void
    {
        $this->uid = $realm->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
        $committe = Committee::findByNameOrFail($realm, $ou);
        $role = $committe->roles()->where('cn', $cn)->firstOrFail();
        $this->description = $role->getFirstAttribute('description');
    }

    public function render(): View
    {
        return view('livewire.committee.edit-role')->title(__('roles.edit_title'));
    }

    public function save(): RedirectResponse
    {
        $this->validate();
        $committe = Committee::findByNameOrFail($this->uid, $this->ou);
        $role = $committe->roles()->where('cn', $this->cn)->firstOrFail();
        $before = $role->getFirstAttribute('description');
        $role->save([
            'description' => $this->description,
        ]);
        resolve(ActivityLogger::class)->recordDescriptionChange('role.updated', $this->uid, $role, $before);

        Flux::toast(variant: 'success', text: __('roles.edit_success', ['role' => $this->cn]));

        return to_route('committees.roles', [
            'realm' => $this->uid,
            'ou' => $this->ou,
            'cn' => $this->cn,
        ]);
    }
}

<?php

namespace App\Livewire\Group;

use App\Ldap\Community;
use Flux\Flux;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\OpenLDAP\Group;
use Livewire\Attributes\Rule;
use Livewire\Component;

class NewGroup extends Component
{
    #[Rule('required|string|min:2|alpha_dash')]
    public string $cn = '';

    #[Rule('required|string|min:2|alpha_dash')]
    public string $realm_uid = '';

    #[Rule('required|min:6')]
    public string $name = '';

    public function mount(Community $realm)
    {
        $this->realm_uid = $realm->getShortCode();
    }

    public function render(): Factory|View|Application
    {
        return view('livewire.group.new-group')->title(__('groups.new_title'));
    }

    public function save()
    {
        $this->validate();
        try {
            $group = new Group([
                'cn' => $this->cn,
                'description' => $this->name,
                'uniqueMember' => '',
            ]);
            $group->setDn("cn=$this->cn,ou=Groups,ou=$this->realm_uid,ou=Communities,{$group->getBaseDn()}");
            $group->save();

            Flux::toast(variant: 'success', text: __('Added new Group'));

            return to_route('realms.groups.roles', ['realm' => $this->realm_uid, 'cn' => $this->cn]);
        } catch (LdapRecordException $exception) {
            $this->addError('cn', $exception->getMessage());

            return false;
        }
    }
}

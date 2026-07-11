<?php

namespace App\Livewire\Realm;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Contracts\Foundation\Application;
use App\Ldap\Community;
use Flux\Flux;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Rule;
use Livewire\Component;

class NewRealm extends Component
{
    #[Rule('required|alpha_dash:ascii')]
    public string $uid = "";

    #[Rule('required|min:6|ascii')]
    public string $name = "";


    public function render(): Factory|View|Application
    {
        return view('livewire.realm.new-realm')
            ->title(__('realms.new_realm_title', ['realm' => $this->uid]));
    }

    public function save()
    {
        $this->validate();
        try {
            $realm = new Community([
                'ou' => $this->uid,
                'description' => $this->name,
            ]);
            $realm->setDn("ou=$this->uid,ou=Communities,{$realm->getBaseDn()}");
            $realm->generateSkeleton();

            Flux::toast(variant: 'success', text: 'Neuer Realm angelegt');
            return to_route('realms.pick');
        } catch (LdapRecordException $exception) {
            $this->addError('uid', $exception->getMessage());
            return false;
        }
    }
}

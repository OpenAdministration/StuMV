<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\OpenLDAP\Group;
use Livewire\Attributes\Rule;
use Livewire\Component;

class NewRealm extends Component
{
    #[Rule('required')]
    public string $uid = "";

    #[Rule('required|min:6')]
    public string $name = "";


    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\Contracts\Foundation\Application
    {
        return view('livewire.realm.new-realm')
            ->title(__('realms.new_realm_title', ['realm' => $this->uid]));
    }

    public function save()
    {
        $this->validate();
        try{
            $realm = new Community([
                'ou' => $this->uid,
                'description' => $this->name,
            ]);
            $realm->setDn("ou=$this->uid,ou=Communities,{$realm->getBaseDn()}");
            $realm->generateSkeleton();

            return redirect()->route('realms.pick')->with('message', 'Neuer Realm angelegt');
        } catch (LdapRecordException $exception){
            $this->addError('uid', $exception->getMessage());
            return false;
        }
    }
}

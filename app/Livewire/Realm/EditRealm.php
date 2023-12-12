<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use Illuminate\Support\Facades\Request;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * @property $realm
 */
class EditRealm extends Component
{
    #[Locked]
    public string $uid = '';

    #[Rule('required|min:6')]
    public string $name = '';

    public function mount(Community $uid): void
    {
        $this->uid = $uid->getFirstAttribute('ou');
        // here is an implicit search for the realm and return 404 if not existent
        $this->name = $this->realm->description[0] ?? "";
    }

    #[Computed(persist: true)]
    public function realm() : Community{
        return Community::findOrFailByUid($this->uid);
    }

    public function render()
    {
        return view('livewire.edit-realm');
    }

    public function save(){
        $r = Community::findOrFailByUid($this->uid);
        $r->description =  [$this->name];
        $r->save();
        return redirect()->route('realms.pick')
            ->with('message', __("Realm :uid has been changed.", ['uid' => $this->uid]));
    }
}

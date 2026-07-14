<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Rules\UniqueRole;
use Flux\Flux;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class NewRole extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    public string $cn;

    public string $description;

    public function mount(Community $realm, $ou)
    {
        $this->uid = $realm->getFirstAttribute('ou');
        $this->ou = $ou;
    }

    public function rules()
    {
        return [
            'cn' => [
                'regex:/^[a-z0-9-]*$/',
                new UniqueRole($this->uid, $this->ou),
            ],
        ];
    }

    public function render(): Application|View|\Illuminate\Foundation\Application|Factory
    {
        return view('livewire.committee.new-role')->title(__('committees.new_role_title', ['committee' => $this->ou]));
    }

    public function updated(): void
    {
        $this->validate();
    }

    public function save()
    {
        $this->validate();
        $c = Committee::fromCommunity($this->uid)->findByOrFail('ou', $this->ou);
        $r = new Role([
            'cn' => $this->cn,
            'description' => $this->description,
            'uniqueMember' => '',
        ]);
        $r->inside($c);
        $r->save();

        Flux::toast(variant: 'success', text: __('New Role created'));

        return to_route('committees.roles', ['ou' => $this->ou, 'realm' => $this->uid]);
    }
}

<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Role;
use App\Rules\UniqueRole;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
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

    public function mount(Community $realm, string $ou): void
    {
        $this->uid = $realm->getFirstAttribute('ou');
        $this->ou = $ou;
    }

    /** @return array<string, mixed> */
    public function rules(): array
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

    public function save(): RedirectResponse
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
        resolve(ActivityLogger::class)->recordEntry('role.created', $this->uid, $r);

        Flux::toast(variant: 'success', text: __('roles.new_role_created'));

        return to_route('committees.roles', ['ou' => $this->ou, 'realm' => $this->uid]);
    }
}

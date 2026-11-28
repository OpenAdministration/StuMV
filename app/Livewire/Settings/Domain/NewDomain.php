<?php

namespace App\Livewire\Settings\Domain;

use App\Ldap\Community;
use App\Ldap\Domain;
use App\Rules\UniqueDomain;
use App\Support\ActivityLogger;
use dacoto\DomainValidator\Validator\Domain as DomainValidator;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class NewDomain extends Component
{
    #[Locked]
    public string $uid;

    #[Validate(as: 'Domain')]
    public string $dc;

    public function mount(Community $realm): void
    {
        $this->authorize('create', [Domain::class, $realm]);
        $this->uid = $realm->getFirstAttribute('ou');
    }

    #[Computed]
    public function community(): Community
    {
        return Community::findOrFailByUid($this->uid);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'dc' => [
                new DomainValidator,
                new UniqueDomain,
            ],

        ];
    }

    public function render(): View
    {
        return view('livewire.settings.domain.new-domain')->title(__('realms.new_domain_title', ['realm' => $this->uid]));
    }

    public function save(): void
    {
        $this->authorize('create', [Domain::class, $this->community()]);
        $this->validate();

        $d = Domain::make([
            'dc' => $this->dc,
        ]);

        $d->setDn("dc=$this->dc,".Domain::dnRoot($this->uid));
        $d->save();
        resolve(ActivityLogger::class)->record('domain.created', $this->uid, $d, ['dn' => $d->getDn()], $this->dc);
        $this->redirectRoute('realms.domains', ['realm' => $this->uid]);
    }
}

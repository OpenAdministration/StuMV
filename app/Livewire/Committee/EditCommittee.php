<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\Dn;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use LdapRecord\Models\Attributes\DistinguishedName;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class EditCommittee extends Component
{
    #[Locked]
    public string $realm_uid;

    #[Locked]
    public string $ou = '';

    #[Locked]
    public string $parent_ou = '';

    #[Validate('required|min:3')]
    public string $description = '';

    public function mount(Community $realm, string $ou): void
    {
        $this->realm_uid = $realm->getFirstAttribute('ou');
        $this->ou = $ou;
        $c = Committee::findByNameOrFail($this->realm_uid, $ou);
        $this->description = $c->getFirstAttribute('description');
        $parentRdn = DistinguishedName::explode(Dn::parentOf($c))[0];
        $this->parent_ou = explode('=', (string) $parentRdn, 2)[1];
        if ($this->parent_ou === 'Committees') {
            $this->parent_ou = '';
        }
    }

    public function render(): View
    {
        $parents = Committee::fromCommunity($this->realm_uid)
            ->whereNotEquals('ou', 'Committees') // remove parent Folder from Results;
            ->get();

        return view('livewire.committee.edit-committee', [
            'select_parents' => $parents,
        ])->title(__('committees.edit_title', ['committee' => $this->ou]));
    }

    public function save(): RedirectResponse
    {
        $this->validate();

        $c = Committee::findByNameOrFail($this->realm_uid, $this->ou);
        $before = $c->getFirstAttribute('description');
        $c->setAttribute('description', $this->description);
        $c->save();
        resolve(ActivityLogger::class)->recordDescriptionChange('committee.updated', $this->realm_uid, $c, $before);

        Flux::toast(variant: 'success', text: __('common.saved'));

        return response()->redirectToRoute('committees.list', ['realm' => $this->realm_uid]);
    }
}

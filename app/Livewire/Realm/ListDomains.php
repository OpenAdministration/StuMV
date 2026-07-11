<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\Domain;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListDomains extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = '';

    #[Url]
    public string $sortDirection = 'asc';

    public string $deleteDomain = '';

    public string $uid;

    public function mount(Community $uid)
    {
        $this->uid = $uid->getFirstAttribute('ou');
    }

    public function render()
    {
        $domainSlice = Domain::fromCommunity($this->uid)
            ->searchFor('ou', $this->search)
            ->slice(1, 10, $this->sortField, $this->sortDirection);

        return view('livewire.realm.list-domains', ['domainSlice' => $domainSlice])
            ->title(__('realms.domains.list_title'));
    }

    public function deletePrepare($dc): void
    {
        $results = Domain::fromCommunity($this->uid)->where('dc', $dc)->get();
        if ($results->count() === 1) {
            $this->deleteDomain = $results->first()->getFirstAttribute('dc');
            Flux::modal('delete')->show();
        }
    }

    public function deleteCommit()
    {
        $results = Domain::fromCommunity($this->uid)->where('dc', $this->deleteDomain)->get();
        if ($results->count() === 1) {
            $results->first()->delete();
            $this->close();
        }
    }

    public function close()
    {
        Flux::modal('delete')->close();
        unset($this->deleteDomain);
    }
}

<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListCommitteesList extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'description';

    #[Url]
    public string $sortDirection = 'asc';

    public string $realm_uid;
    public bool $ready = false;

    public function mount(Community $uid): void
    {
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function loadCommittees(): void
    {
        $this->ready = true;
    }

    public function sortBy($field): void
    {
        if ($this->sortField === $field) {
            // toggle direction
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
            $this->sortField = $field;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $community = Community::findByUid($this->realm_uid);

        $committees = Committee::fromCommunity($this->realm_uid)
            ->orderBy($this->sortField, $this->sortDirection)
            ->get();

        return view('livewire.committee.list-committees-list', [
            'committees' => $committees,
            'community' => $community,
        ])->title(__('committees.list_title'));
    }
}

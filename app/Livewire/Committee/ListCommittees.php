<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListCommittees extends Component
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

    public function mount(Community $uid): void
    {
        $this->realm_uid = $uid->getFirstAttribute('ou');
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
            ->orderBy($this->sortField)
            ->list()
            ->get();

        $search = trim($this->search);
        if ($search !== '') {
            $search = mb_strtolower($search);
            $committees = $committees->filter(function (Committee $committee) use ($search): bool {
                return $this->committeeMatchesSearch($committee, $search);
            })->values();
        }

        return view('livewire.committee.list', [
            'committees' => $committees,
            'community' => $community,
        ])->title(__('committees.list_title'));
    }

    protected function committeeMatchesSearch(Committee $committee, string $search): bool
    {
        $values = array_filter([
            $committee->getFirstAttribute('ou'),
            $committee->getFirstAttribute('description'),
        ]);

        foreach ($values as $value) {
            if (mb_stripos(mb_strtolower($value), $search) !== false) {
                return true;
            }
        }

        foreach ($committee->descendants()->get() as $descendant) {
            if ($this->committeeMatchesSearch($descendant, $search)) {
                return true;
            }
        }

        return false;
    }
}

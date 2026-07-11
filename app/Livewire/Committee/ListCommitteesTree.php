<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListCommitteesTree extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

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

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $community = Community::findByUid($this->realm_uid);

        if (! $this->ready) {
            return view('livewire.committee.list-committees-tree', [
                'committees' => collect(),
                'community' => $community,
            ])->title(__('committees.list_title'));
        }

        $committees = Committee::fromCommunity($this->realm_uid)->list()->get();

        $search = trim($this->search);
        if ($search !== '') {
            $search = mb_strtolower($search);
            $committees = $committees->filter(fn(Committee $committee): bool => $this->committeeMatchesSearch($committee, $search))->values();
        }

        return view('livewire.committee.list-committees-tree', [
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
            if (mb_stripos(mb_strtolower((string) $value), $search) !== false) {
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

<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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
    public string $sortField = 'name';
    #[Url]
    public string $sortDirection = 'asc';

    public bool $showDeleteModal = false;

    public string $realm_uid;

    public string $deleteCommitteeDn;
    public string $deleteCommitteeName;
    public string $deleteCommitteeOu;

    public string $deleteConfirmText;

    public function mount($uid): void
    {
        $this->realm_uid = $uid;
    }

    public function sortBy($field): void
    {
        if($this->sortField === $field){
            // toggle direction
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        }else{
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
        $committeesSlice = Committee::fromCommunity($this->realm_uid)
            ->search('ou', $this->search)
            ->orderBy($this->sortField, $this->sortDirection)
            ->slice(1, 10);

        return view('livewire.committee.list', [
            'committeesSlice' => $committeesSlice,
        ])->layout('layouts.app', ['headline' => __('Committees')]);
    }


    public function deletePrepare(string $dn): void
    {
        $c = Committee::findOrFail($dn);
        $this->deleteCommitteeDn = $dn;
        $this->deleteCommitteeName = $c->getFirstAttribute('description');
        $this->deleteCommitteeOu = $c->getFirstAttribute('ou');
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $c = Committee::findOrFail($this->deleteCommitteeDn);
        if ($this->deleteConfirmText !== $c->getFirstAttribute('ou')){
            $this->addError('deleteConfirmText', __('Does not equal :text', $c->getFirstAttribute('ou')));
            return;
        }
        $c->delete(recursive: true);

        $this->close();
    }

    public function close(): void
    {
        unset($this->deleteCommitteeDn);
        $this->showDeleteModal = false;
    }

}

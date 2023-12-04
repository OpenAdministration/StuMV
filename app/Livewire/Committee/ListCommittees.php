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
    public string $sortField = 'ou';
    #[Url]
    public string $sortDirection = 'asc';

    public bool $showDeleteModal = false;

    public string $realm_uid;

    public string $deleteCommitteeDn;
    public string $deleteCommitteeName;
    public string $deleteCommitteeOu;

    public string $deleteConfirmText;

    public function mount(Community $uid): void
    {
        $this->realm_uid = $uid->getFirstAttribute('ou');
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

    #[Title('Committees')]
    public function render()
    {
        $committeesSlice = Committee::fromCommunity($this->realm_uid)
            ->search('ou', $this->search)
            ->orderBy('ou:caseIgnoreIA5Match', 'asc')
            ->slice(1, 100);

        return view('livewire.committee.list', [
            'committeesSlice' => $committeesSlice,
        ]);
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

<?php

namespace App\Livewire\Committee;

use App\Models\Committee;
use App\Models\Realm;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class Crud extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';
    public string $sortField = 'name';
    public string $sortDirection = 'asc';

    public bool $showEditModal = false;
    public bool $showNewModal = false;
    public bool $showDeleteModal = false;

    public Committee $editCommittee;
    public Committee $newCommittee;
    public Committee $deleteCommittee;

    public string $editCommitteeOldName = '';
    public string $deleteCommitteeName = '';

    public array $rules = [];

    protected $queryString = ['search', 'sortField', 'sortDirection'];

    public function sortBy($field){
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
        $user = Auth::user();

        if ($user?->is_superuser) {
            $committees = Committee::query()->search('name', $this->search)->search('realm_uid', $this->search)
                ->orderBy($this->sortField, $this->sortDirection)
                ->paginate(10);
            $realms = Realm::all();
        } else {
            $committees = Committee::query()->search('name', $this->search)
                ->whereIn('realm_uid', $user->admin_realms->modelKeys())
                ->search('realm_uid', $this->search)
                ->orderBy($this->sortField, $this->sortDirection)
                ->paginate(10);
            $realms = $user->admin_realms()->get();
        }

        return view('livewire.committee.list', [
            'committees' => $committees,
            'realms' => $realms,
        ])->layout('layouts.app', ['headline' => __('Committees')]);
    }

    public function edit($id): void
    {
        $this->rules = [
            'editCommittee.name' => 'required',
            'editCommittee.parent_committee_id' => 'required',
        ];
        $this->editCommittee = Committee::find($id);
        $this->editCommitteeOldName = $this->editCommittee->name;
        $this->showEditModal = true;
    }

    public function new(): void
    {
        $this->rules = [
            'newCommittee.name' => 'required',
            'newCommittee.realm_uid' => 'required',
            'newCommittee.parent_committee_id' => 'required',
        ];
        $this->newCommittee = new Committee();
        $this->newCommittee->realm_uid = 'please-select';
        $this->newCommittee->parent_committee_id = 'please-select';
        $this->showNewModal = true;
    }

    public function deletePrepare($id): void
    {
        $this->deleteCommittee = Committee::find($id);
        $this->deleteCommitteeName = $this->deleteCommittee->name;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $this->authorize('delete', $this->deleteCommittee);
        $this->deleteCommittee->delete();

        // reset everything to prevent a 404 modal
        unset($this->deleteCommittee);
        unset($this->newCommittee);
        unset($this->editCommittee);

        $this->showDeleteModal = false;
    }

    public function close(): void
    {
        $this->showEditModal = false;
        $this->showNewModal = false;
        $this->showDeleteModal = false;
    }

    public function saveEdit(): void
    {
        $this->validate();

        if ($this->editCommittee->parent_committee_id == 'null') {
            $this->editCommittee->parent_committee_id = null;
        } else {
            if ($this->editCommittee->parent_committee_id == $this->editCommittee->id) {
                $this->addError('editCommittee.parent_committee_id', 'Ein Gremium kann sich selbst nicht übergeordnet sein!');
                return;
            }

            $parentCommittee = Committee::find($this->editCommittee->parent_committee_id);

            if (empty($parentCommittee) || $parentCommittee->realm_uid != $this->editCommittee->realm_uid) {
                $this->addError('editCommittee.parent_committee_id', 'Ungültiges übergeordnetes Gremium!');
                return;
            }
        }

        $this->authorize('update', $this->editCommittee);

        $this->editCommittee->save();
        $this->showEditModal = false;
    }

    public function saveNew(): void
    {
        $this->validate();

        if ($this->newCommittee->parent_committee_id == 'null') {
            $this->newCommittee->parent_committee_id = null;
        } else {
            $parentCommittee = Committee::find($this->newCommittee->parent_committee_id);

            if (empty($parentCommittee) || $parentCommittee->realm_uid != $this->newCommittee->realm_uid) {
                $this->addError('newCommittee.parent_committee_id', 'Ungültiges übergeordnetes Gremium!');

                // reset both selects because otherwise livewire does not remember the value of the other one
                $this->newCommittee->realm_uid = 'please-select';
                $this->newCommittee->parent_committee_id = 'please-select';

                return;
            }
        }

        $committeeRealm = Realm::find($this->newCommittee->realm_uid);

        if (empty($committeeRealm)) {
            $this->addError('newCommittee.realm_uid', 'Ungültiger Realm!');

            // reset both selects because otherwise livewire does not remember the value of the other one
            $this->newCommittee->realm_uid = 'please-select';
            $this->newCommittee->parent_committee_id = 'please-select';

            return;
        }

        $this->authorize('create', [Committee::class, $committeeRealm]);

        $this->newCommittee->save();
        $this->showNewModal = false;
    }
}

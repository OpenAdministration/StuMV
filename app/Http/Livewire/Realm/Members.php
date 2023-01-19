<?php

namespace App\Http\Livewire\Realm;

use App\Models\Realm;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class Members extends Component {

    use WithPagination;

    public string $search = '';
    public string $sortField = 'full_name';
    public string $sortDirection = 'asc';

    public Realm $realm;

    public bool $showNewModal = false;
    public bool $showDeleteModal = false;

    public User $newMember;
    public User $deleteMember;

    public string $deleteMemberName = '';

    public array $rules = [];

    protected $queryString = ['search', 'sortField', 'sortDirection'];

    public function mount($uid) {
        $this->realm = Realm::findOrFail($uid);
    }

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

    public function render() {
        return view(
            'livewire.realm.members', [
                'realm' => $this->realm,
                'realm_members' => User::join('realm_user_relation', 'id', '=', 'user_id')
                    ->search('full_name', $this->search)
                    ->where('realm_uid', $this->realm->uid)
                    ->search('username', $this->search)
                    ->where('realm_uid', $this->realm->uid)
                    ->orderBy($this->sortField, $this->sortDirection)
                    ->paginate(10),
                // all users that don't belong to this realm
                'free_members' => User::all()->except($this->realm->members->modelKeys()),
            ]
        )->layout('layouts.app', [
            'headline' => __('realms.members_heading', [
                'name' => $this->realm->long_name,
                'uid' => $this->realm->uid
            ])
        ]);
    }

    public function new(): void
    {
        $this->rules = [
            'newMember.id' => 'required|integer',
        ];

        $this->newMember = new User();
        $this->showNewModal = true;
    }

    public function saveNew(): void
    {
        $this->validate();

        $newMember = User::find($this->newMember->id);

        if (empty($newMember)) {
            $this->addError('newMember.id', 'Benutzer nicht gefunden!');
            $this->showNewModal = false;
            return;
        }

        $userBelongsToRealm = $newMember->realms()->where('uid', $this->realm->uid)->exists();

        if($userBelongsToRealm) {
            $this->addError('newMember.id', 'Ungültiger Benutzer!');
            $this->showNewModal = false;
            return;
        }

        $this->realm->members()->attach($newMember);
        $this->realm->refresh();
        $this->showNewModal = false;
    }

    public function deletePrepare($id): void
    {
        $this->deleteMember = User::find($id);


        $userBelongsToRealm = $this->deleteMember->realms()->where('uid', $this->realm->uid)->exists();

        if(!$userBelongsToRealm) {
            // only allow deletes from the same realm
            unset($this->deleteMember);
            return;
        }

        $this->deleteMemberName = $this->deleteMember->full_name;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $this->realm->members()->detach($this->deleteMember);
        $this->realm->refresh();

        // reset everything to prevent a 404 modal
        unset($this->newMember);
        unset($this->deleteMember);

        $this->showDeleteModal = false;
    }

    public function close(): void
    {
        $this->showNewModal = false;
        $this->showDeleteModal = false;
    }
}

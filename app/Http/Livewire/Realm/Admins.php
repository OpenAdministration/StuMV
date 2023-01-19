<?php

namespace App\Http\Livewire\Realm;

use App\Models\Realm;
use App\Models\User;
use Livewire\Component;
use Livewire\WithPagination;

class Admins extends Component {

    use WithPagination;

    public string $search = '';
    public string $sortField = 'full_name';
    public string $sortDirection = 'asc';

    public Realm $realm;

    public bool $showNewModal = false;
    public bool $showDeleteModal = false;

    public User $newAdmin;
    public User $deleteAdmin;

    public string $deleteAdminName = '';

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
            'livewire.realm.admins', [
                'realm' => $this->realm,
                'realm_admins' => User::join('realm_admin_relation', 'id', '=', 'user_id')
                    ->search('full_name', $this->search)
                    ->where('realm_uid', $this->realm->uid)
                    ->search('username', $this->search)
                    ->where('realm_uid', $this->realm->uid)
                    ->orderBy($this->sortField, $this->sortDirection)
                    ->paginate(10),
                // all users that aren't admins on this realm
                'free_admins' => User::all()->except($this->realm->admins->modelKeys()),
            ]
        )->layout('layouts.app', [
            'headline' => __('realms.admins_heading', [
                'name' => $this->realm->long_name,
                'uid' => $this->realm->uid
            ])
        ]);
    }

    public function new(): void
    {
        $this->rules = [
            'newAdmin.id' => 'required|integer',
        ];

        $this->newAdmin = new User();
        $this->showNewModal = true;
    }

    public function saveNew(): void
    {
        $this->validate();

        $newAdmin = User::find($this->newAdmin->id);

        if (empty($newAdmin)) {
            $this->addError('newAdmin.id', 'Benutzer nicht gefunden!');
            $this->showNewModal = false;
            return;
        }

        $userBelongsToRealm = $newAdmin->admin_realms()->where('uid', $this->realm->uid)->exists();

        if($userBelongsToRealm) {
            $this->addError('newAdmin.id', 'Ungültiger Benutzer!');
            $this->showNewModal = false;
            return;
        }

        $this->realm->admins()->attach($newAdmin);
        $this->realm->refresh();
        $this->showNewModal = false;
    }

    public function deletePrepare($id): void
    {
        $this->deleteAdmin = User::find($id);


        $userBelongsToRealm = $this->deleteAdmin->admin_realms()->where('uid', $this->realm->uid)->exists();

        if(!$userBelongsToRealm) {
            // only allow deletes from the same realm
            unset($this->deleteAdmin);
            return;
        }

        $this->deleteAdminName = $this->deleteAdmin->full_name;
        $this->showDeleteModal = true;
    }

    public function deleteCommit(): void
    {
        $this->realm->admins()->detach($this->deleteAdmin);
        $this->realm->refresh();

        // reset everything to prevent a 404 modal
        unset($this->newAdmin);
        unset($this->deleteAdmin);

        $this->showDeleteModal = false;
    }

    public function close(): void
    {
        $this->showNewModal = false;
        $this->showDeleteModal = false;
    }
}

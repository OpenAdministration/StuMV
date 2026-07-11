<?php

namespace App\Livewire;

use App\Ldap\SuperUserGroup;
use App\Ldap\User;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListSuperUsers extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'cn';

    #[Url]
    public string $sortDirection = 'asc';

    public bool $showDeleteModal = false;

    public string $deleteAdminName = '';

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
        $superadmins = SuperUserGroup::group()->members()->get();

        return view('livewire.list-super-admins', [
            'superadmins' => $superadmins,
        ])->title(__('superadmins.list_title'));
    }

    public function deletePrepare($username): void
    {
        $this->deleteAdminName = $username;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        $user = User::findByUsername($this->deleteAdminName);
        SuperUserGroup::group()->members()->detach($user);

        // reset everything to prevent a 404 modal
        $this->close();
    }

    public function close(): void
    {
        unset($this->deleteAdminName);
        Flux::modal('delete')->close();
    }
}

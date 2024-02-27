<?php

namespace App\Livewire\Group;

use Livewire\Component;

class EditGroup extends Component
{
    public function mount($cn) :void
    {

    }

    public function render()
    {
        return view('livewire.group.edit-group')->title(__('groups.roles_add_title'));
    }
}

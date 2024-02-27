<?php

namespace App\Livewire;

use Livewire\Component;

class AddSuperUser extends Component
{
    public function render()
    {
        return view('livewire.add-super-admins')->title(__('superadmins.new_title'));
    }
}

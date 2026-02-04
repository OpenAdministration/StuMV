<?php

namespace App\Livewire\Tools;

use App\Ldap\Community;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ToolsDashboard extends Component
{
    #[Locked]
    public string $uid;

    public function mount(Community $uid)
    {
        $this->uid = $uid->getFirstAttribute('ou');
    }

    public function render()
    {
        return view('livewire.tools.tools-dashboard');
    }
}

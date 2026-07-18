<?php

namespace App\Livewire\Tools;

use App\Ldap\Community;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ToolsDashboard extends Component
{
    #[Locked]
    public string $uid;

    public bool $unildapDataExists = false;

    public function mount(Community $realm)
    {
        $this->uid = $realm->getFirstAttribute('ou');
        $this->unildapDataExists = filled(config('ldap.connections.uni.base_dn'));
    }

    public function render()
    {
        return view('livewire.tools.tools-dashboard');
    }
}

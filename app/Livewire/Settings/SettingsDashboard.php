<?php

namespace App\Livewire\Settings;

use App\Ldap\Community;
use Livewire\Attributes\Locked;
use Livewire\Component;

class SettingsDashboard extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $name;

    #[Locked]
    public bool $isAdminRealm;

    public function mount(Community $realm): void
    {
        $this->uid = $realm->getShortCode();
        $this->name = $realm->getFirstAttribute('description') ?: $this->uid;
        $this->isAdminRealm = $realm->isAdminRealm();
    }

    public function render()
    {
        return view('livewire.settings.settings-dashboard')
            ->title(__('realms.settings'));
    }
}

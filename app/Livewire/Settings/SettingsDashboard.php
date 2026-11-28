<?php

namespace App\Livewire\Settings;

use App\Ldap\Community;
use App\Support\MailmanClient;
use Illuminate\Contracts\View\View;
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

    public function render(): View
    {
        return view('livewire.settings.settings-dashboard', [
            'mailmanDomains' => $this->isAdminRealm && MailmanClient::listCreationEnabled(),
        ])
            ->title(__('realms.settings'));
    }
}

<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class CommunityDashboard extends Component
{
    public string $uid;

    public function mount(Community $realm): void
    {
        $this->uid = $realm->getShortCode();
    }

    public function render(): View
    {
        $community = Community::findOrFailByUid($this->uid);
        $name = $community->getFirstAttribute('description');

        return view('livewire.realm.community-dashboard', [
            'community' => $community,
            'name' => $name,
        ])->title(__('realms.dashboard.headline', ['name' => $name]));
    }
}

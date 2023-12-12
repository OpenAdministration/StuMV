<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use Livewire\Attributes\Url;
use Livewire\Component;

class CommunityDashboard extends Component
{
    public string $uid;

    public function mount(?Community $uid){
        $this->uid = $uid?->getShortCode() ?? session('realm_uid');
    }
    public function render()
    {
        $community = Community::findOrFailByUid($this->uid);
        $name = $community->getFirstAttribute('description');

        return view('livewire.realm.community-dashboard', [
            'community' => $community,
            'name' => $name,
        ]);
    }
}

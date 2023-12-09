<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use Livewire\Attributes\Url;
use Livewire\Component;

class CommunityDashboard extends Component
{
    public string $uid;

    public function mount(?Community $uid){
        dd('dash');
        $this->uid = $uid?->getShortCode() ?? session('realm_uid');
    }
    public function render()
    {
        return view('livewire.realm.community-dashboard');
    }
}

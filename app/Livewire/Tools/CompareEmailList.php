<?php

namespace App\Livewire\Tools;

use App\Ldap\Community;
use App\Ldap\User;
use Livewire\Attributes\Locked;
use Livewire\Component;

class CompareEmailList extends Component
{
    #[Locked]
    public string $uid;

    public string $emailAddressesInput = "";

    public array $matches = [];

    public bool $comparisonCompleted = false;

    public bool $noMatches = false;

    public function mount(Community $uid)
    {
        $this->uid = $uid->getFirstAttribute('ou');
    }

    public function render()
    {
        return view('livewire.tools.compare-email-list');
    }

    public function compareEmailAddressesWithLdap()
    {
        $this->comparisonCompleted = false;
        $this->noMatches = false;
        $this->matches = [];

        $emailAddresses = preg_split("/\r\n|\n|\r/", $this->emailAddressesInput);

        foreach ($emailAddresses as $email) {
            $user = User::findByEmail($email);
            if ($user !== null) {
                $this->matches[] = [
                    'uid' => $user->getFirstAttribute('uid'),
                    'cn' => $user->getFirstAttribute('cn'),
                    'email' => $user->getFirstAttribute('mail'),
                ];
            }
        }

        if (count($this->matches) < 1) {
            $this->noMatches = true;
        }

        $this->comparisonCompleted = true;
    }
}

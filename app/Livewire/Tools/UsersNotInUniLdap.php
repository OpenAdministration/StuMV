<?php

namespace App\Livewire\Tools;

use App\Ldap\Community;
use App\Ldap\Domain;
use App\Ldap\User;
use App\Models\UniLdap;
use Livewire\Attributes\Locked;
use Livewire\Component;

class UsersNotInUniLdap extends Component
{
    #[Locked]
    public string $uid;

    public bool $unildapDataExists = false;

    public array $results = [];

    public bool $comparisonCompleted = false;

    public function mount(Community $uid)
    {
        $this->uid = $uid->getFirstAttribute('ou');
        $unildap = UniLdap::where('realm', $this->uid)->first();
        if ($unildap !== null) {
            $this->unildapDataExists = true;
        }
    }

    public function render()
    {
        return view('livewire.tools.users-not-in-uni-ldap');
    }

    public function searchForUsersNotInUniLdap()
    {
        $this->comparisonCompleted = false;
        $this->results = [];

        $members = \App\Models\User::select('email')->where('realm', $this->uid)->orderBy('full_name')->get();

        $domains = [];
        $domainEntries = Domain::fromCommunity($this->uid)->get();
        foreach ($domainEntries as $item) {
            $domains[] = [$item->dc];
        }

        $unildap = UniLdap::where('realm', $this->uid)->first();

        foreach ($members as $member) {
            $memberEmailParts = explode('@', $member->email);
            if (in_array($memberEmailParts[1], $domains)) {
                $ds = ldap_connect($unildap->host);
                if ($ds) {
                    $filter = "(|(mail=$member->email))";
                    $result = ldap_search($ds, $unildap->members_base, $filter);
                    $info = ldap_get_entries($ds, $result);
                    if (!$info['count'] == 1) {
                        $entry = $info[0];
                        if (is_array($entry) && isset($entry['mail']) && !is_array($entry['mail'])) {
                            $entry['mail'] = [$entry['mail']];
                        }

                        $user = User::findByEmail($entry['mail']);
                        if ($user !== null) {
                            $this->results[] = [
                                'uid' => $user->getFirstAttribute('uid'),
                                'cn' => $user->getFirstAttribute('cn'),
                                'email' => $user->getFirstAttribute('mail'),
                            ];
                        }
                    }
                }
            }
        }

        $this->comparisonCompleted = true;
    }
}

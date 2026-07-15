<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\User;
use Flux\Flux;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Rule;
use Livewire\Component;

class NewCommitteeModerator extends Component
{
    #[Rule('required')]
    public array $dn = [];

    #[Rule('required|string')]
    public string $realm_uid = '';

    #[Rule('required|string')]
    public string $ou = '';

    public function mount(Community $realm, string $ou): void
    {
        $this->realm_uid = $realm->getFirstAttribute('ou');
        $this->ou = $ou;
    }

    public function render(): Factory|View|Application
    {
        $community = Community::findOrFailByUid($this->realm_uid);
        $committee = Committee::findByNameOrFail($this->realm_uid, $this->ou);
        $this->authorize('moderator', [$committee, $community]);

        $userList = $community->membersGroup()->members()->get();
        $moderators = $committee->moderatorsGroup()->members()->get();
        // baseCollection does like strings in contains, ldapCollection does not...
        $moderatorDns = $moderators->modelDns()->toBase();
        $selectable_users = $userList->filter(fn ($user) => $moderatorDns->doesntContain($user->getDn()));

        return view('livewire.committee.new-committee-moderator', [
            'committee' => $committee,
            'selectable_users' => $selectable_users,
        ]);
    }

    public function save()
    {
        $this->validate();

        $community = Community::findOrFailByUid($this->realm_uid);
        $committee = Committee::findByNameOrFail($this->realm_uid, $this->ou);
        $this->authorize('moderator', [$committee, $community]);

        foreach ($this->dn as $dn) {
            try {
                $user = User::findOrFail($dn);
                $committee->moderatorsGroup()->members()->attach($user);

                Flux::toast(variant: 'success', text: __('common.added_new_moderator'));
            } catch (LdapRecordException $exception) {
                $this->addError('dn', $exception->getMessage());

                return false;
            }
        }

        return to_route('committees.moderators', ['realm' => $this->realm_uid, 'ou' => $this->ou]);
    }
}

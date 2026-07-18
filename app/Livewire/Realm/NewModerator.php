<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User;
use Flux\Flux;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use LdapRecord\LdapRecordException;
use Livewire\Attributes\Rule;
use Livewire\Component;

class NewModerator extends Component
{
    #[Rule('required')]
    public array $dn = [];

    #[Rule('required|string')]
    public string $realm_uid = '';

    public function mount(Community $realm): void
    {
        $this->realm_uid = $realm->getFirstAttribute('ou');
    }

    public function render(): Factory|View|Application
    {
        $c = Community::findOrFailByUid($this->realm_uid);
        $userList = $c->membersGroup()->members()->get();
        $moderators = $c->moderatorsGroup()->members()->get();
        // baseCollection does like strings in contains, ldapCollection does not...
        $moderatorDns = $moderators->modelDns()->toBase();
        $selectable_users = $userList->filter(fn ($user) => $moderatorDns->doesntContain($user->getDn()))
            ->sortBy(fn ($user): string => mb_strtolower((string) $user->getFirstAttribute('cn')), SORT_NATURAL)
            ->values();

        return view('livewire.realm.new-moderator', [
            'community' => $c,
            'selectable_users' => $selectable_users,
        ]);
    }

    public function save()
    {
        $this->validate();
        foreach ($this->dn as $dn) {
            try {
                $user = User::findOrFail($dn);
                $realm = Community::findOrFailByUid($this->realm_uid);
                $realm->moderatorsGroup()->members()->attach($user);

                Flux::toast(variant: 'success', text: __('common.added_new_moderator'));
            } catch (LdapRecordException $exception) {
                $this->addError('dn', $exception->getMessage());

                return false;
            }
        }

        return to_route('realms.mods', ['realm' => $this->realm_uid]);
    }
}

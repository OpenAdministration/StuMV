<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\User;
use App\Models\RoleMembership;
use App\Rules\UserIsMember;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AddUserToRole extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public string $ou;

    #[Locked]
    public string $cn;

    #[Validate]
    public array $usernames = [];

    #[Validate('date:Y-m-d')]
    public ?string $start_date = null;

    #[Validate('date:Y-m-d|nullable')]
    public ?string $end_date = null;

    #[Validate('date:Y-m-d|nullable')]
    public ?string $decision_date = null;

    #[Validate('string|nullable')]
    public string $comment = '';

    public function mount(Community $realm, $ou, $cn)
    {
        $this->uid = $realm->getFirstAttribute('ou');
        $this->ou = $ou;
        $this->cn = $cn;
        $this->start_date = today()->format('Y-m-d');
        $this->decision_date = today()->format('Y-m-d');
    }

    public function rules()
    {
        return [
            'usernames.*' => [
                'required',
                new UserIsMember($this->uid),
            ],
        ];
    }

    public function render()
    {
        $c = Community::findByOrFail('ou', $this->uid);
        $users = User::query()->in($c->peopleDn())->get();

        $committee = Committee::findByName($this->uid, $this->ou);
        $role = $committee?->roles()->where('cn', $this->cn)->first();
        $roleName = $role?->getFirstAttribute('description') ?: $this->cn;

        // Exclude users who are already an active member of this role.
        $existingUsernames = $role
            ? $role->dbMemberships()->active(today())->pluck('username')
            : collect();
        $selectableUsers = $users->filter(fn ($user) => ! $existingUsernames->contains($user->getFirstAttribute('uid')))
            ->sortBy(fn ($user): string => mb_strtolower((string) $user->getFirstAttribute('cn')), SORT_NATURAL)
            ->values();

        return view('livewire.committee.add-user-to-role', ['users' => $selectableUsers, 'roleName' => $roleName])
            ->title(__('realms.add_members_to_role_title', ['role' => $roleName]));
    }

    public function save()
    {
        $this->validate();

        $committee = Committee::findByName($this->uid, $this->ou);

        foreach ($this->usernames as $username) {
            RoleMembership::create([
                'role_cn' => $this->cn,
                'committee_dn' => $committee->getDn(),
                'realm' => $this->uid,
                'username' => $username,
                'from' => $this->start_date,
                'until' => ! empty($this->end_date) ? $this->end_date : null,
                'decided' => ! empty($this->decision_date) ? $this->decision_date : null,
                'comment' => ! empty($this->comment) ? $this->comment : null,
            ]);
            Flux::toast(variant: 'success', text: __('roles.added_user', ['username' => $username, 'role' => $this->cn]));
        }

        return to_route('committees.roles.members', [
            'realm' => $this->uid,
            'ou' => $this->ou,
            'cn' => $this->cn,
        ]);
    }

    public function updateDecisionDate()
    {
        if ($this->start_date) {
            $this->decision_date = $this->start_date;
        }
    }
}

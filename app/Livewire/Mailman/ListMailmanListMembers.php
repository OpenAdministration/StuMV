<?php

namespace App\Livewire\Mailman;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\User as LdapUser;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use App\Support\MailmanClient;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class ListMailmanListMembers extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'display_name';

    #[Url]
    public string $sortDirection = 'asc';

    #[Locked]
    public string $uid;

    #[Locked]
    public string $mailmanListId;

    public function mount(Community $realm, string $listId): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getShortCode();
        $this->mailmanListId = $listId;
    }

    public function sortBy($field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
            $this->sortField = $field;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render(MailmanClient $mailman)
    {
        $mappings = GroupMailmanList::where('realm', $this->uid)
            ->where('mailman_list_id', $this->mailmanListId)
            ->get();

        abort_if($mappings->isEmpty(), 404);

        $groupsByDn = Group::query()->findMany($mappings->pluck('group_dn')->unique()->all())
            ->keyBy(fn (Group $group): string => $group->getDn());

        // Same DB-driven desired-membership pipeline as mailman:sync-lists
        // (role_group_relation -> role_user_relation), scoped to just the
        // group(s) mapped to this particular list.
        $memberships = RoleMembership::active()->where('realm', $this->uid)->get();
        $membershipsByRole = $memberships->groupBy(fn (RoleMembership $m): string => $m->committee_dn.'|'.$m->role_cn);

        $usersByUsername = LdapUser::query()->in(Community::peopleDnFor($this->uid))
            ->whereIn('uid', $memberships->pluck('username')->unique()->all())
            ->get()
            ->keyBy(fn (LdapUser $user): string => $user->getFirstAttribute('uid'));

        $desiredByEmail = [];
        foreach ($mappings as $mapping) {
            $group = $groupsByDn->get($mapping->group_dn);
            if (! $group) {
                continue;
            }

            $groupRoles = GroupMembership::where('group_dn', $mapping->group_dn)->get();
            foreach ($groupRoles as $groupRole) {
                $roleCn = str_replace('cn=', '', substr((string) $groupRole->role_dn, 0, strpos((string) $groupRole->role_dn, ',')));
                $committeeDn = strstr((string) $groupRole->role_dn, 'ou=');
                $key = $committeeDn.'|'.$roleCn;

                foreach ($membershipsByRole->get($key, collect()) as $membership) {
                    $user = $usersByUsername->get($membership->username);
                    $mail = $user?->getFirstAttribute('mail');
                    if (! $mail) {
                        continue;
                    }

                    $desiredByEmail[$mail] ??= ['user' => $user, 'groups' => []];
                    $desiredByEmail[$mail]['groups'][$group->getFirstAttribute('cn')] = true;
                }
            }
        }

        $fetchFailed = false;
        $actualByEmail = [];
        try {
            foreach ($mailman->listMembers($this->mailmanListId) as $entry) {
                $actualByEmail[$entry['email']] = true;
            }
        } catch (Throwable $e) {
            $fetchFailed = true;
            Log::warning('Mailman listMembers failed', ['list_id' => $this->mailmanListId, 'exception' => $e->getMessage()]);
        }

        $rows = collect();
        foreach ($desiredByEmail as $email => $data) {
            $rows->push([
                'email' => $email,
                'user' => $data['user'],
                'groups' => array_keys($data['groups']),
                'status' => $fetchFailed ? 'unknown' : (isset($actualByEmail[$email]) ? 'synced' : 'pending'),
            ]);
        }

        if (! $fetchFailed) {
            $staleEmails = array_diff(array_keys($actualByEmail), array_keys($desiredByEmail));
            $staleUsersByEmail = empty($staleEmails)
                ? collect()
                : LdapUser::query()->in(Community::peopleDnFor($this->uid))->whereIn('mail', $staleEmails)->get()
                    ->keyBy(fn (LdapUser $user): string => $user->getFirstAttribute('mail'));

            foreach ($staleEmails as $email) {
                $rows->push([
                    'email' => $email,
                    'user' => $staleUsersByEmail->get($email),
                    'groups' => [],
                    'status' => 'stale',
                ]);
            }
        }

        $rows = $rows->map(fn (array $row): array => $row + [
            'display_name' => $row['user']?->getFirstAttribute('cn') ?: $row['email'],
        ]);

        if ($this->search !== '') {
            $search = mb_strtolower($this->search);
            $rows = $rows->filter(fn (array $row): bool => str_contains(mb_strtolower((string) $row['display_name']), $search)
                || str_contains(mb_strtolower($row['email']), $search));
        }

        $sorted = $rows
            ->sortBy(fn (array $row) => mb_strtolower((string) $row[$this->sortField]), SORT_NATURAL, $this->sortDirection === 'desc')
            ->values();

        $perPage = 10;
        $page = $this->getPage();
        $members = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );

        return view('livewire.mailman.list-mailman-list-members', [
            'members' => $members,
            'fetchFailed' => $fetchFailed,
        ])->title(__('group_mailman_lists.members_title', ['name' => $this->mailmanListId]));
    }
}

<?php

namespace App\Livewire\Mailman;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\User as LdapUser;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use App\Support\MailmanClient;
use Flux\Flux;
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
    public string $status = '';

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

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    /**
     * Adds or removes just this one address, after re-checking server-side
     * that it really is pending or stale - the address comes from the client.
     */
    public function syncMember(string $email): void
    {
        $mapping = $this->mapping();
        $group = Group::find($mapping->group_dn);

        if (! $group) {
            Flux::toast(variant: 'danger', text: __('group_mailman_lists.group_missing'));

            return;
        }

        $mailman = resolve(MailmanClient::class);
        $isDesired = array_key_exists($email, $this->desiredMembers($mapping));

        try {
            $current = collect($mailman->listMembers($this->mailmanListId))->firstWhere('email', $email);
        } catch (Throwable $e) {
            report($e);
            Flux::toast(variant: 'danger', text: __('group_mailman_lists.member_sync_failed', ['email' => $email]));

            return;
        }

        if ($isDesired && $current === null) {
            $failed = $mailman->subscribe($this->mailmanListId, [$email]);
            $success = __('group_mailman_lists.member_sync_added', ['email' => $email]);
        } elseif (! $isDesired && $current !== null) {
            $failed = $mailman->unsubscribe($this->mailmanListId, [$current]);
            $success = __('group_mailman_lists.member_sync_removed', ['email' => $email]);
        } else {
            Flux::toast(text: __('group_mailman_lists.member_sync_unchanged', ['email' => $email]));

            return;
        }

        if ($failed === []) {
            Flux::toast(variant: 'success', text: $success);
        } else {
            Log::error('Mailman single member sync failed', ['list_id' => $this->mailmanListId, 'email' => $email, 'exception' => $failed[$email] ?? null]);
            Flux::toast(variant: 'danger', text: __('group_mailman_lists.member_sync_failed', ['email' => $email]));
        }
    }

    public function render(MailmanClient $mailman)
    {
        $mapping = $this->mapping();
        $group = Group::find($mapping->group_dn);
        $desiredByEmail = $group ? $this->desiredMembers($mapping) : [];

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
        foreach ($desiredByEmail as $email => $user) {
            $rows->push([
                'email' => $email,
                'user' => $user,
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
                    'status' => 'stale',
                ]);
            }
        }

        $rows = $rows->map(fn (array $row): array => $row + [
            'display_name' => $row['user']?->getFirstAttribute('cn') ?: $row['email'],
        ]);

        $statusCounts = $rows->countBy('status')->all();

        if ($this->status !== '') {
            $rows = $rows->where('status', $this->status);
        }

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
            'statusCounts' => $statusCounts,
            'groupCn' => $group?->getFirstAttribute('cn') ?? $mapping->group_dn,
            'groupExists' => $group !== null,
        ])->title(__('group_mailman_lists.members_title', ['name' => $this->mailmanListId]));
    }

    private function mapping(): GroupMailmanList
    {
        return GroupMailmanList::where('realm', $this->uid)
            ->where('mailman_list_id', $this->mailmanListId)
            ->firstOrFail();
    }

    /**
     * Same DB-driven desired-membership pipeline as mailman:sync-lists
     * (role_group_relation -> role_user_relation), scoped to just the group
     * mapped to this particular list.
     *
     * @return array<string, LdapUser> keyed by mail
     */
    private function desiredMembers(GroupMailmanList $mapping): array
    {
        $memberships = RoleMembership::active()->where('realm', $this->uid)->get();
        $membershipsByRole = $memberships->groupBy(fn (RoleMembership $m): string => $m->committee_dn.'|'.$m->role_cn);

        $usersByUsername = LdapUser::query()->in(Community::peopleDnFor($this->uid))
            ->whereIn('uid', $memberships->pluck('username')->unique()->all())
            ->get()
            ->keyBy(fn (LdapUser $user): string => $user->getFirstAttribute('uid'));

        $desiredByEmail = [];
        foreach (GroupMembership::where('group_dn', $mapping->group_dn)->get() as $groupRole) {
            $roleCn = str_replace('cn=', '', substr((string) $groupRole->role_dn, 0, strpos((string) $groupRole->role_dn, ',')));
            $committeeDn = strstr((string) $groupRole->role_dn, 'ou=');
            $key = $committeeDn.'|'.$roleCn;

            foreach ($membershipsByRole->get($key, collect()) as $membership) {
                $user = $usersByUsername->get($membership->username);
                $mail = $user?->getFirstAttribute('mail');
                if ($mail) {
                    $desiredByEmail[$mail] = $user;
                }
            }
        }

        return $desiredByEmail;
    }
}

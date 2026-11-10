<?php

namespace App\Livewire\Mailman;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Ldap\User as LdapUser;
use App\Models\GroupMailmanList;
use App\Support\MailmanClient;
use App\Support\MailmanDesiredMembers;
use App\Support\MailmanListSyncStatus;
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

        if (! $this->memberGroupsExist($mapping)) {
            Flux::toast(variant: 'danger', text: __('group_mailman_lists.group_missing'));

            return;
        }

        if (! $mapping->enabled) {
            Flux::toast(variant: 'danger', text: __('group_mailman_lists.list_disabled', ['name' => $this->mailmanListId]));

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

        MailmanListSyncStatus::forget($mapping);

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
        $groupsExist = $this->memberGroupsExist($mapping);
        ['users' => $desiredByEmail, 'sources' => $sourcesByEmail] = $groupsExist
            ? resolve(MailmanDesiredMembers::class)->withSources($mapping)
            : ['users' => [], 'sources' => []];

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
                'groups' => array_map(Group::shortNameFromDn(...), $sourcesByEmail[$email] ?? []),
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
                    'groups' => [],
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
            'groupCn' => implode(', ', array_map(Group::shortNameFromDn(...), $mapping->groupDns('member'))),
            'groupExists' => $groupsExist,
            'enabled' => $mapping->enabled,
        ])->title(__('group_mailman_lists.members_title', ['name' => $this->mailmanListId]));
    }

    private function mapping(): GroupMailmanList
    {
        return GroupMailmanList::where('realm', $this->uid)
            ->where('mailman_list_id', $this->mailmanListId)
            ->firstOrFail();
    }

    private function memberGroupsExist(GroupMailmanList $mapping): bool
    {
        $groupDns = $mapping->groupDns('member');

        return $groupDns !== [] && collect($groupDns)->every(fn (string $dn): bool => Group::find($dn) !== null);
    }

    /**
     * @return array<string, LdapUser> keyed by mail
     */
    private function desiredMembers(GroupMailmanList $mapping): array
    {
        return resolve(MailmanDesiredMembers::class)->for($mapping);
    }
}

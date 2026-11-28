<?php

namespace App\Livewire\Mailman;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMailmanList;
use App\Models\MailmanDomainRealm;
use App\Support\MailmanClient;
use Flux\Flux;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Throwable;

/**
 * Creates a mapping, or edits one when a listId is given.
 *
 * @property-read bool $canCreateList
 * @property-read ?array $domains
 */
class NewGroupMailmanList extends Component
{
    #[Locked]
    public string $uid = '';

    #[Locked]
    public ?int $mappingId = null;

    /** @var array<int, string> group short names */
    public array $member_groups = [];

    /** @var array<int, string> */
    public array $moderator_groups = [];

    public string $mailman_list_id = '';

    /** 'existing' or 'new' */
    #[Url(as: 'list', except: 'existing')]
    public string $list_source = 'existing';

    public string $list_name = '';

    public string $mail_host = '';

    public function mount(Community $realm, ?string $listId = null): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getShortCode();

        if ($listId !== null) {
            $mapping = GroupMailmanList::where('realm', $this->uid)->where('mailman_list_id', $listId)->with('groups')->firstOrFail();
            $this->mappingId = $mapping->id;
            $this->mailman_list_id = $mapping->mailman_list_id;
            $this->member_groups = array_map(Group::shortNameFromDn(...), $mapping->groupDns('member'));
            $this->moderator_groups = array_map(Group::shortNameFromDn(...), $mapping->groupDns('moderator'));
        }

        $this->updatedListSource();
    }

    #[Computed]
    public function canCreateList(): bool
    {
        return $this->mappingId === null && MailmanDomainRealm::creationAllowedFor($this->uid);
    }

    /**
     * @return array<int, string>|null null if Mailman could not be asked
     */
    #[Computed]
    public function domains(): ?array
    {
        try {
            return array_values(array_intersect(resolve(MailmanClient::class)->domains(), MailmanDomainRealm::allowedFor($this->uid)));
        } catch (Throwable $e) {
            Log::warning("Mailman: could not fetch domains: {$e->getMessage()}");

            return null;
        }
    }

    public function updatedListSource(): void
    {
        if ($this->creatingList() && $this->mail_host === '' && count($this->domains ?? []) === 1) {
            $this->mail_host = $this->domains[0];
        }
    }

    protected function rules(): array
    {
        $groupCns = $this->groupCns();

        $list = $this->creatingList()
            ? [
                'list_name' => ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9._+-]*$/'],
                'mail_host' => ['required', 'string', Rule::in($this->domains ?? [])],
            ]
            : [
                // Across realms too: two mappings would keep undoing each other's sync.
                'mailman_list_id' => ['required', 'string', 'max:255', Rule::unique('group_mailman_lists', 'mailman_list_id')->ignore($this->mappingId)],
            ];

        return $list + [
            'member_groups' => ['required', 'array', 'min:1'],
            'member_groups.*' => ['string', Rule::in($groupCns)],
            'moderator_groups' => ['array'],
            'moderator_groups.*' => ['string', Rule::in($groupCns)],
        ];
    }

    protected function messages(): array
    {
        return [
            'mailman_list_id.unique' => __('group_mailman_lists.list_already_mapped'),
            'list_name.regex' => __('group_mailman_lists.list_name_invalid'),
        ];
    }

    public function render()
    {
        $groups = Group::query()->in(Group::dnRoot($this->uid))->get();

        return view('livewire.mailman.new-group-mailman-list', ['groups' => $groups])
            ->title(__($this->mappingId ? 'group_mailman_lists.edit_title' : 'group_mailman_lists.new_title'));
    }

    public function save()
    {
        $this->validate();

        $createdList = $this->creatingList();
        if ($createdList) {
            $this->mailman_list_id = $this->createList();
        }

        $existing = $this->mappingId ? GroupMailmanList::where('realm', $this->uid)->findOrFail($this->mappingId) : null;
        $before = $existing?->activityLogSnapshot();
        $mapping = $existing
            ? tap($existing)->update(['mailman_list_id' => $this->mailman_list_id])
            : GroupMailmanList::create(['realm' => $this->uid, 'mailman_list_id' => $this->mailman_list_id]);

        $dnsByCn = Group::query()->in(Group::dnRoot($this->uid))->get()
            ->mapWithKeys(fn (Group $group): array => [(string) $group->getFirstAttribute('cn') => (string) $group->getDn()]);
        $toDns = fn (array $cns): array => array_map(fn (string $cn): string => $dnsByCn[$cn], $cns);
        $mapping->syncGroups([
            'member' => $toDns($this->member_groups),
            'moderator' => $toDns($this->moderator_groups),
        ]);
        $mapping->recordAction($before === null ? 'created' : 'updated', before: $before);

        Flux::toast(variant: 'success', text: match (true) {
            $createdList => __('group_mailman_lists.list_created_success', ['name' => $this->mailman_list_id]),
            $this->mappingId !== null => __('group_mailman_lists.updated_success'),
            default => __('group_mailman_lists.created_success'),
        });

        return to_route('realms.group-mailman-lists', ['realm' => $this->uid]);
    }

    private function creatingList(): bool
    {
        return $this->list_source === 'new' && $this->canCreateList;
    }

    private function createList(): string
    {
        $name = strtolower($this->list_name);

        if (GroupMailmanList::where('mailman_list_id', "{$name}.{$this->mail_host}")->exists()) {
            throw ValidationException::withMessages(['list_name' => __('group_mailman_lists.list_already_mapped')]);
        }

        try {
            return resolve(MailmanClient::class)->createList($name, $this->mail_host, config('services.mailman.list_owner') ?: null);
        } catch (RequestException $e) {
            // Mailman answers "Mailing list exists" with a 400.
            if ($e->response->status() === 400 && str_contains($e->response->body(), 'exists')) {
                throw ValidationException::withMessages(['list_name' => __('group_mailman_lists.list_exists')]);
            }
            Log::warning("Mailman: could not create {$name}@{$this->mail_host}: {$e->getMessage()}");
        } catch (Throwable $e) {
            Log::warning("Mailman: could not create {$name}@{$this->mail_host}: {$e->getMessage()}");
        }

        throw ValidationException::withMessages(['list_name' => __('group_mailman_lists.list_create_failed')]);
    }

    /**
     * @return array<int, string>
     */
    private function groupCns(): array
    {
        return Group::query()->in(Group::dnRoot($this->uid))->get()
            ->map(fn (Group $group): string => (string) $group->getFirstAttribute('cn'))
            ->all();
    }
}

<?php

namespace App\Livewire\Mailman;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMailmanList;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Creates a mapping, or edits one when a listId is given.
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
    }

    protected function rules(): array
    {
        $groupCns = $this->groupCns();

        return [
            'member_groups' => ['required', 'array', 'min:1'],
            'member_groups.*' => ['string', Rule::in($groupCns)],
            'moderator_groups' => ['array'],
            'moderator_groups.*' => ['string', Rule::in($groupCns)],
            // Across realms too: two mappings would keep undoing each other's sync.
            'mailman_list_id' => ['required', 'string', 'max:255', Rule::unique('group_mailman_lists', 'mailman_list_id')->ignore($this->mappingId)],
        ];
    }

    protected function messages(): array
    {
        return [
            'mailman_list_id.unique' => __('group_mailman_lists.list_already_mapped'),
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

        $mapping = $this->mappingId
            ? tap(GroupMailmanList::where('realm', $this->uid)->findOrFail($this->mappingId))->update(['mailman_list_id' => $this->mailman_list_id])
            : GroupMailmanList::create(['realm' => $this->uid, 'mailman_list_id' => $this->mailman_list_id]);

        $dnsByCn = Group::query()->in(Group::dnRoot($this->uid))->get()
            ->mapWithKeys(fn (Group $group): array => [(string) $group->getFirstAttribute('cn') => (string) $group->getDn()]);
        $toDns = fn (array $cns): array => array_map(fn (string $cn): string => $dnsByCn[$cn], $cns);
        $mapping->syncGroups([
            'member' => $toDns($this->member_groups),
            'moderator' => $toDns($this->moderator_groups),
        ]);

        Flux::toast(variant: 'success', text: __($this->mappingId ? 'group_mailman_lists.updated_success' : 'group_mailman_lists.created_success'));

        return to_route('realms.group-mailman-lists', ['realm' => $this->uid]);
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

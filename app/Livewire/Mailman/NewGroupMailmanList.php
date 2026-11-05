<?php

namespace App\Livewire\Mailman;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMailmanList;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Component;

class NewGroupMailmanList extends Component
{
    public string $uid = '';

    public string $group_cn = '';

    public string $mailman_list_id = '';

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getShortCode();
    }

    protected function rules(): array
    {
        return [
            'group_cn' => ['required', 'string'],
            // Across realms too: two mappings would keep undoing each other's sync.
            'mailman_list_id' => ['required', 'string', 'max:255', Rule::unique('group_mailman_lists', 'mailman_list_id')],
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
            ->title(__('group_mailman_lists.new_title'));
    }

    public function save()
    {
        $this->validate();

        $group = Group::findOrFail(Group::dnFrom($this->uid, $this->group_cn));

        GroupMailmanList::create([
            'realm' => $this->uid,
            'group_dn' => $group->getDn(),
            'mailman_list_id' => $this->mailman_list_id,
        ]);

        Flux::toast(variant: 'success', text: __('group_mailman_lists.created_success'));

        return to_route('realms.group-mailman-lists', ['realm' => $this->uid]);
    }
}

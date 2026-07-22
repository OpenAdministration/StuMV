<?php

namespace App\Livewire\Mailman;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMailmanList;
use Flux\Flux;
use Livewire\Component;

class NewGroupMailmanList extends Component
{
    public string $uid = '';

    public array $group_cns = [];

    public string $mailman_list_id = '';

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getShortCode();
    }

    protected function rules(): array
    {
        return [
            'group_cns' => ['required', 'array', 'min:1'],
            'group_cns.*' => ['string'],
            'mailman_list_id' => ['required', 'string', 'max:255', function ($attribute, $value, $fail): void {
                foreach ($this->group_cns as $group_cn) {
                    if (GroupMailmanList::where('group_dn', Group::dnFrom($this->uid, $group_cn))
                        ->where('mailman_list_id', $value)
                        ->exists()) {
                        $fail(__('group_mailman_lists.mapping_exists', ['group' => $group_cn]));

                        return;
                    }
                }
            }],
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

        foreach ($this->group_cns as $group_cn) {
            $group = Group::findOrFail(Group::dnFrom($this->uid, $group_cn));

            GroupMailmanList::create([
                'realm' => $this->uid,
                'group_dn' => $group->getDn(),
                'mailman_list_id' => $this->mailman_list_id,
            ]);
        }

        Flux::toast(variant: 'success', text: __('group_mailman_lists.created_success'));

        return to_route('realms.group-mailman-lists', ['realm' => $this->uid]);
    }
}

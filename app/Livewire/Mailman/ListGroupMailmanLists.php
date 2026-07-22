<?php

namespace App\Livewire\Mailman;

use App\Ldap\Community;
use App\Ldap\Group;
use App\Models\GroupMailmanList;
use Flux\Flux;
use Livewire\Component;

class ListGroupMailmanLists extends Component
{
    public string $uid;

    public ?int $deleteMappingId = null;

    public string $deleteMappingLabel = '';

    public function mount(Community $realm): void
    {
        abort_if($realm->isAdminRealm(), 404);
        $this->uid = $realm->getShortCode();
    }

    public function render()
    {
        $mappings = GroupMailmanList::where('realm', $this->uid)->orderBy('mailman_list_id')->get()
            ->map(function (GroupMailmanList $mapping) {
                $group = Group::find($mapping->group_dn);

                return (object) [
                    'id' => $mapping->id,
                    'group_cn' => $group?->getFirstAttribute('cn'),
                    'group_description' => $group?->getFirstAttribute('description'),
                    'mailman_list_id' => $mapping->mailman_list_id,
                ];
            });

        return view('livewire.mailman.list-group-mailman-lists', ['mappings' => $mappings])
            ->title(__('group_mailman_lists.list_title'));
    }

    public function deletePrepare(int $mappingId): void
    {
        $mapping = GroupMailmanList::where('realm', $this->uid)->findOrFail($mappingId);
        $this->deleteMappingId = $mapping->id;
        $this->deleteMappingLabel = $mapping->mailman_list_id;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        GroupMailmanList::where('realm', $this->uid)->findOrFail($this->deleteMappingId)->delete();

        Flux::toast(variant: 'success', text: __('group_mailman_lists.deleted_success'));
        $this->closeDelete();
    }

    public function closeDelete(): void
    {
        Flux::modal('delete')->close();
        unset($this->deleteMappingId, $this->deleteMappingLabel);
    }
}

<?php

namespace App\Livewire\Settings\MembershipExport;

use App\Ldap\Community;
use App\Models\MembershipExportTemplate;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ListMembershipExportTemplates extends Component
{
    #[Locked]
    public string $uid;

    public ?int $deleteId = null;

    public string $deleteName = '';

    public function mount(Community $realm): void
    {
        $this->uid = $realm->getShortCode();
    }

    public function render()
    {
        return view('livewire.settings.membership-export.list-membership-export-templates', [
            'templates' => MembershipExportTemplate::forRealm($this->uid)->get(),
        ])->title(__('realms.membership_export.title', ['realm' => $this->uid]));
    }

    public function deletePrepare(int $id): void
    {
        $template = MembershipExportTemplate::where('realm', $this->uid)->findOrFail($id);
        $this->deleteId = $template->id;
        $this->deleteName = $template->name;
        Flux::modal('delete')->show();
    }

    public function deleteCommit(): void
    {
        MembershipExportTemplate::where('realm', $this->uid)->findOrFail($this->deleteId)->delete();

        Flux::toast(variant: 'success', text: __('realms.membership_export.deleted'));
        $this->closeDelete();
    }

    public function closeDelete(): void
    {
        Flux::modal('delete')->close();
        $this->reset('deleteId', 'deleteName');
    }
}

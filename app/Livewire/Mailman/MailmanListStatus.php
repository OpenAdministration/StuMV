<?php

namespace App\Livewire\Mailman;

use App\Models\GroupMailmanList;
use App\Support\MailmanListSyncStatus;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

// Lazy: every row asks Mailman on its own, so the overview itself renders immediately.
#[Lazy]
class MailmanListStatus extends Component
{
    #[Locked]
    public string $uid;

    #[Locked]
    public int $mappingId;

    public function placeholder(): string
    {
        return '<div class="flex items-center"><flux:icon.loading variant="mini" class="text-zinc-400" /></div>';
    }

    public function render(MailmanListSyncStatus $status)
    {
        $mapping = GroupMailmanList::where('realm', $this->uid)->findOrFail($this->mappingId);

        return view('livewire.mailman.mailman-list-status', $status->for($mapping));
    }
}

<?php

namespace App\Livewire\Settings\Domain;

use App\Ldap\Community;
use App\Ldap\Domain;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListDomains extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortField = 'dc';

    #[Url]
    public string $sortDirection = 'asc';

    public string $deleteDomain = '';

    #[Locked]
    public string $uid;

    public function mount(Community $realm): void
    {
        $this->uid = $realm->getFirstAttribute('ou');
    }

    #[Computed]
    public function community(): Community
    {
        return Community::findOrFailByUid($this->uid);
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            // toggle direction
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

    public function render(): View
    {
        $sorted = Domain::fromCommunity($this->uid)
            ->searchFor('dc', $this->search)
            ->get()
            ->sortBy(fn ($domain): string => mb_strtolower((string) $domain->getFirstAttribute($this->sortField)), SORT_NATURAL, $this->sortDirection === 'desc')
            ->values();

        $perPage = 10;
        $page = $this->getPage();
        $domains = new LengthAwarePaginator(
            $sorted->forPage($page, $perPage)->values(),
            $sorted->count(),
            $perPage,
            $page,
        );

        return view('livewire.settings.domain.list-domains', [
            'domains' => $domains,
            'community' => $this->community(),
        ])
            ->title(__('realms.domains.list_title'));
    }

    public function deletePrepare(string $dc): void
    {
        $this->authorize('delete', [Domain::class, $this->community()]);
        $results = Domain::fromCommunity($this->uid)->where('dc', $dc)->get();
        if ($results->count() === 1) {
            $this->deleteDomain = $results->first()->getFirstAttribute('dc');
            Flux::modal('delete')->show();
        }
    }

    public function deleteCommit(): void
    {
        $this->authorize('delete', [Domain::class, $this->community()]);
        $results = Domain::fromCommunity($this->uid)->where('dc', $this->deleteDomain)->get();
        if ($results->count() === 1) {
            $domain = $results->first();
            $domain->delete();
            resolve(ActivityLogger::class)->record('domain.deleted', $this->uid, $domain, ['dn' => $domain->getDn()], $domain->getFirstAttribute('dc'));
            $this->close();
        }
    }

    public function close(): void
    {
        Flux::modal('delete')->close();
        $this->reset('deleteDomain');
    }
}

<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ListCommitteesTree extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $realm_uid;

    public bool $ready = false;

    /** @var list<string> DNs of the committees currently unfolded in the tree. */
    public array $unfolded = [];

    public string $committeeToDelete = '';

    public string $committeeToDeleteOu = '';

    public string $committeeToDeleteDescription = '';

    public string $deleteConfirmText = '';

    public function mount(Community $uid): void
    {
        $this->realm_uid = $uid->getFirstAttribute('ou');
    }

    public function loadCommittees(): void
    {
        $this->ready = true;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function toggleChildren(string $dn): void
    {
        if (in_array($dn, $this->unfolded, true)) {
            $this->unfolded = array_values(array_diff($this->unfolded, [$dn]));
        } else {
            $this->unfolded[] = $dn;
        }
    }

    public function confirmDeleteCommittee(string $dn): void
    {
        $community = Community::findByUid($this->realm_uid);
        $committee = Committee::findOrFail($dn);

        $this->authorize('delete', [$committee, $community]);

        $this->committeeToDelete = $dn;
        $this->committeeToDeleteOu = $committee->getFirstAttribute('ou');
        $this->committeeToDeleteDescription = $committee->getFirstAttribute('description');
        $this->deleteConfirmText = '';
        Flux::modal('delete-committee')->show();
    }

    public function deleteCommittee()
    {
        $community = Community::findByUid($this->realm_uid);
        $c = Committee::findOrFail($this->committeeToDelete);

        $this->authorize('delete', [$c, $community]);

        if ($this->deleteConfirmText !== $c->getFirstAttribute('ou')) {
            $this->addError('deleteConfirmText', __('Does not equal :text', ['text' => $c->getFirstAttribute('ou')]));

            return;
        }

        // Delete role memberships and role group relashionships for this committee and its descendants
        $dn = $c->getDn();
        $roles = $c->roles()->get();
        foreach ($roles as $role) {
            RoleMembership::where('role_cn', $role->getFirstAttribute('cn'))
                ->where('committee_dn', $dn)
                ->delete();
            GroupMembership::where('role_dn', $role->getDn())
                ->delete();
        }

        $descendants = $c->descendants()->get();
        foreach ($descendants as $descendant) {
            $roles = $descendant->roles()->get();
            foreach ($roles as $role) {
                RoleMembership::where('role_cn', $role->getFirstAttribute('cn'))
                    ->where('committee_dn', $descendant->getDn())
                    ->delete();
                GroupMembership::where('role_dn', $role->getDn())
                    ->delete();
            }
        }

        // Delete the committee and its descendants recursively
        $c->delete(recursive: true);

        Flux::modal('delete-committee')->close();

        return to_route('committees.list', [
            'uid' => $this->realm_uid,
        ]);
    }

    public function render()
    {
        $community = Community::findByUid($this->realm_uid);

        if (! $this->ready) {
            return view('livewire.committee.list-committees-tree', [
                'nodes' => [],
                'community' => $community,
            ])->title(__('committees.list.headline', ['name' => $community->getFirstAttribute('description')]));
        }

        $committees = $this->sortByName(Committee::fromCommunity($this->realm_uid)->list()->get());

        $search = mb_strtolower(trim($this->search));

        $nodes = $committees
            ->map(fn (Committee $committee): ?array => $this->buildNode($committee, $search))
            ->filter()
            ->values()
            ->all();

        return view('livewire.committee.list-committees-tree', [
            'nodes' => $nodes,
            'community' => $community,
        ])->title(__('committees.list.headline', ['name' => $community->getFirstAttribute('description')]));
    }

    /**
     * Builds the tree node data for a committee, recursing into children only
     * while they're unfolded (or, while searching, while a match might still
     * be found further down). A collapsed, non-matching branch never has its
     * children's full attributes fetched from LDAP - only a cheap existence
     * check to decide whether to show an expand arrow.
     *
     * Returns null while searching if neither this committee nor any of its
     * descendants match, so the branch is dropped entirely.
     *
     * @return array{committee: Committee, hasChildren: bool, unfolded: bool, children: array}|null
     */
    protected function buildNode(Committee $committee, string $search): ?array
    {
        $isUnfoldedByUser = $search === '' && in_array($committee->getDn(), $this->unfolded, true);

        // Collapsed and not searching: we only need to know whether an expand
        // arrow should be shown, not the full (possibly large) child list.
        if (! $isUnfoldedByUser && $search === '') {
            return [
                'committee' => $committee,
                'hasChildren' => $committee->descendants()->select(['ou'])->exists(),
                'unfolded' => false,
                'children' => [],
            ];
        }

        $children = $this->sortByName($committee->descendants()->get());

        $childNodes = $children
            ->map(fn (Committee $child): ?array => $this->buildNode($child, $search))
            ->filter()
            ->values()
            ->all();

        $ownMatches = $search !== '' && $this->committeeOwnMatches($committee, $search);

        if ($search !== '' && ! $ownMatches && empty($childNodes)) {
            return null;
        }

        // While searching, auto-unfold every branch that survived filtering so
        // matches are visible without having to toggle down to them manually.
        $unfolded = $search !== '' ? true : $isUnfoldedByUser;

        return [
            'committee' => $committee,
            'hasChildren' => $search !== '' ? ! empty($childNodes) : $children->isNotEmpty(),
            'unfolded' => $unfolded,
            'children' => $childNodes,
        ];
    }

    /**
     * Sorted client-side (rather than via an LDAP orderBy) because the
     * production directory doesn't support the sssvlv sort control.
     */
    protected function sortByName(Collection $committees): Collection
    {
        return $committees
            ->sortBy(fn (Committee $committee): string => mb_strtolower((string) $committee->getFirstAttribute('description')), SORT_NATURAL)
            ->values();
    }

    protected function committeeOwnMatches(Committee $committee, string $search): bool
    {
        $values = array_filter([
            $committee->getFirstAttribute('ou'),
            $committee->getFirstAttribute('description'),
        ]);
        return array_any($values, fn($value) => mb_stripos(mb_strtolower((string) $value), $search) !== false);
    }
}

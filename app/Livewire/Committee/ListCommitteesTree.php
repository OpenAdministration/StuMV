<?php

namespace App\Livewire\Committee;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Models\GroupMembership;
use App\Models\RoleMembership;
use Flux\Flux;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
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
            ])->title(__('committees.list_title'));
        }

        $committees = Committee::fromCommunity($this->realm_uid)->list()->get();

        $search = trim($this->search);
        if ($search !== '') {
            $search = mb_strtolower($search);
            $committees = $committees->filter(fn (Committee $committee): bool => $this->committeeMatchesSearch($committee, $search))->values();
        }

        $nodes = $committees->map(fn (Committee $committee): array => $this->buildNode($committee, $search))->all();

        return view('livewire.committee.list-committees-tree', [
            'nodes' => $nodes,
            'community' => $community,
        ])->title(__('committees.list_title'));
    }

    /**
     * Builds the tree node data for a committee, recursing into its children
     * only while they are unfolded (mirrors what was previously fetched lazily
     * by each nested Livewire component instance).
     *
     * @return array{committee: Committee, hasChildren: bool, unfolded: bool, children: array}
     */
    protected function buildNode(Committee $committee, string $search): array
    {
        $children = $committee->descendants()->get();

        if ($search !== '') {
            $children = $children->filter(fn (Committee $child): bool => $this->committeeMatchesSearch($child, $search))->values();
        }

        // While searching, auto-unfold every branch that survived the filter so
        // matches are visible without having to toggle down to them manually.
        $unfolded = $search !== ''
            ? $children->isNotEmpty()
            : in_array($committee->getDn(), $this->unfolded, true);

        return [
            'committee' => $committee,
            'hasChildren' => $children->isNotEmpty(),
            'unfolded' => $unfolded,
            'children' => $unfolded
                ? $children->map(fn (Committee $child): array => $this->buildNode($child, $search))->all()
                : [],
        ];
    }

    protected function committeeMatchesSearch(Committee $committee, string $search): bool
    {
        $values = array_filter([
            $committee->getFirstAttribute('ou'),
            $committee->getFirstAttribute('description'),
        ]);

        foreach ($values as $value) {
            if (mb_stripos(mb_strtolower((string) $value), $search) !== false) {
                return true;
            }
        }

        foreach ($committee->descendants()->get() as $descendant) {
            if ($this->committeeMatchesSearch($descendant, $search)) {
                return true;
            }
        }

        return false;
    }
}

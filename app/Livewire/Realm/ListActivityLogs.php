<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Models\ActivityLog;
use App\Models\RealmIdentityProvider;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Lang;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The activity log of a realm; in the admin realm every realm's, for superadmins only.
 */
class ListActivityLogs extends Component
{
    use WithPagination;

    public string $uid;

    public bool $allRealms = false;

    #[Url]
    public string $search = '';

    /** @var list<string> */
    #[Url]
    public array $actions = [];

    #[Url]
    public string $from = '';

    #[Url]
    public string $until = '';

    public function mount(Community $realm): void
    {
        $this->uid = $realm->getShortCode();
        $this->allRealms = $realm->isAdminRealm();
        abort_if($this->allRealms && ! auth()->user()->ldap()->isSuperAdmin(), 403);
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $logs = $this->query()->latest()->orderByDesc('id')->paginate(25);

        return view('livewire.realm.list-activity-logs', [
            'logs' => $logs,
            'actors' => $this->actorNames($logs),
            'people' => $this->personNames($logs),
            'actionOptions' => $this->scoped(ActivityLog::query())->distinct()->pluck('action')
                ->mapWithKeys(fn (string $action): array => [$action => __('activity_log.actions.'.$action)])
                ->sort(),
        ])->title(__('activity_log.title'));
    }

    /** The field's translated name, its key when there is none. */
    public function fieldLabel(string $field): string
    {
        return Lang::has('activity_log.fields.'.$field) ? __('activity_log.fields.'.$field) : $field;
    }

    /** A logged value as text, for the details column. */
    public function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null, $value === [] => '–',
            is_bool($value) => __($value ? 'activity_log.yes' : 'activity_log.no'),
            is_array($value) => implode(', ', array_map(
                fn (mixed $item): string => is_scalar($item) ? (string) $item : (string) json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $value,
            )),
            default => (string) $value,
        };
    }

    /** @return Builder<ActivityLog> */
    private function query(): Builder
    {
        return $this->scoped(ActivityLog::query())
            ->when($this->actions !== [], fn (Builder $q) => $q->whereIn('action', $this->actions))
            ->when($this->search !== '', fn (Builder $q) => $q->where('subject_label', 'like', '%'.$this->search.'%'))
            ->when($this->from !== '', fn (Builder $q) => $q->whereDate('created_at', '>=', $this->from))
            ->when($this->until !== '', fn (Builder $q) => $q->whereDate('created_at', '<=', $this->until));
    }

    /**
     * @param  Builder<ActivityLog>  $query
     * @return Builder<ActivityLog>
     */
    private function scoped(Builder $query): Builder
    {
        return $this->allRealms ? $query : $query->where('realm', $this->uid);
    }

    /**
     * Names of the page's actors, keyed by "type:id"; deleted ones are missing.
     *
     * @return array<string, string>
     */
    private function actorNames(LengthAwarePaginator $logs): array
    {
        $ids = fn (string $type) => collect($logs->items())->where('actor_type', $type)->pluck('actor_id')->unique()->all();

        $users = User::whereIn('uid', $ids('user'))->get()
            ->mapWithKeys(fn (User $user): array => ['user:'.$user->uid => "{$user->full_name} ({$user->username})"]);
        $providers = RealmIdentityProvider::whereIn('id', $ids('identity_provider'))->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, string $id): array => ['identity_provider:'.$id => $name]);

        return [...$users->all(), ...$providers->all()];
    }

    /**
     * Names of the people the page's details refer to by entryUUID, while their account exists.
     *
     * @return array<string, string>
     */
    private function personNames(LengthAwarePaginator $logs): array
    {
        $uids = collect($logs->items())->pluck('details.person')->filter()->unique()->all();

        return User::whereIn('uid', $uids)->get()
            ->mapWithKeys(fn (User $user): array => [$user->uid => "{$user->full_name} ({$user->username})"])
            ->all();
    }
}

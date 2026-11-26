<?php

namespace App\Livewire\Realm;

use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\ActivityLog;
use App\Models\RealmIdentityProvider;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
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

    /** The browser's, so the filter times match the local times shown. */
    public string $timezone = 'UTC';

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
            'people' => $this->people($logs),
            'providers' => $this->providerNames($logs),
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
            ->when($this->filterTime($this->from), fn (Builder $q, CarbonImmutable $from) => $q->where('created_at', '>=', $from))
            // the whole minute given
            ->when($this->filterTime($this->until), fn (Builder $q, CarbonImmutable $until) => $q->where('created_at', '<', $until->addMinute()));
    }

    /** A datetime-local value in the browser's timezone, as UTC; null when empty or invalid. */
    private function filterTime(string $value): ?CarbonImmutable
    {
        $timezone = in_array($this->timezone, timezone_identifiers_list(), true) ? $this->timezone : 'UTC';

        try {
            return $value === '' ? null : CarbonImmutable::parse($value, $timezone)->utc();
        } catch (InvalidFormatException) {
            return null;
        }
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
     * The people the page refers to by entryUUID (as actor, subject or detail), while their account exists.
     *
     * @return array<string, array{name: string, url: string}>
     */
    private function people(LengthAwarePaginator $logs): array
    {
        $logs = collect($logs->items());
        $uids = $logs->where('actor_type', 'user')->pluck('actor_id')
            ->merge($logs->where('subject_type', LdapUser::class)->pluck('subject_id'))
            ->merge($logs->pluck('details.person'))
            ->filter()->unique()->all();

        return User::whereIn('uid', $uids)->get()
            ->mapWithKeys(fn (User $user): array => [$user->uid => [
                'name' => $user->full_name,
                'url' => route('profile', ['realm' => $user->realm, 'username' => $user->username]),
            ]])
            ->all();
    }

    /** @return array<string, string> */
    private function providerNames(LengthAwarePaginator $logs): array
    {
        $ids = collect($logs->items())->where('actor_type', 'identity_provider')->pluck('actor_id')->unique()->all();

        return RealmIdentityProvider::whereIn('id', $ids)->pluck('name', 'id')->all();
    }
}

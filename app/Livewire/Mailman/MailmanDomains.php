<?php

namespace App\Livewire\Mailman;

use App\Ldap\Community;
use App\Models\MailmanDomainRealm;
use App\Support\MailmanClient;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

/**
 * Super admins decide which realm may create lists under which Mailman domain.
 */
class MailmanDomains extends Component
{
    #[Locked]
    public string $uid = '';

    #[Locked]
    public bool $fetchFailed = false;

    /** @var array<int, array{mail_host: string, realms: array<int, string>}> */
    public array $allowed = [];

    public function mount(Community $realm): void
    {
        abort_unless($realm->isAdminRealm() && MailmanClient::listCreationEnabled(), 404);
        $this->uid = $realm->getShortCode();

        $this->fetchFailed = $this->domains === null;
        $stored = MailmanDomainRealm::all()->groupBy('mail_host');
        foreach ($this->domains ?? [] as $domain) {
            $this->allowed[] = ['mail_host' => $domain, 'realms' => $stored->get($domain)?->pluck('realm')->all() ?? []];
        }
    }

    /**
     * @return array<int, string>|null null if Mailman could not be asked
     */
    #[Computed]
    public function domains(): ?array
    {
        try {
            return resolve(MailmanClient::class)->domains();
        } catch (Throwable $e) {
            Log::warning("Mailman: could not fetch domains: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * @return array<string, string> uid => name
     */
    #[Computed]
    public function communities(): array
    {
        return Community::query()->setDn(Community::$rootDn)->list()->get()
            ->reject(fn (Community $community): bool => $community->isAdminRealm())
            ->mapWithKeys(fn (Community $community): array => [$community->getShortCode() => $community->getLongName() ?: $community->getShortCode()])
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->all();
    }

    protected function rules(): array
    {
        return [
            'allowed' => ['array'],
            'allowed.*.mail_host' => ['required', 'string', Rule::in($this->domains ?? [])],
            'allowed.*.realms' => ['array'],
            'allowed.*.realms.*' => ['string', Rule::in(array_keys($this->communities))],
        ];
    }

    public function render()
    {
        return view('livewire.mailman.mailman-domains')
            ->title(__('mailman_domains.title'));
    }

    public function save(): void
    {
        $this->validate();

        DB::transaction(function (): void {
            foreach ($this->allowed as ['mail_host' => $domain, 'realms' => $realms]) {
                $realms = array_values(array_unique($realms));
                MailmanDomainRealm::where('mail_host', $domain)->whereNotIn('realm', $realms)->delete();
                foreach ($realms as $realm) {
                    MailmanDomainRealm::firstOrCreate(['mail_host' => $domain, 'realm' => $realm]);
                }
            }
        });

        Flux::toast(variant: 'success', text: __('mailman_domains.saved_success'));
    }
}

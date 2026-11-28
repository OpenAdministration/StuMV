<?php

namespace App\Livewire\Settings;

use App\Ldap\Community;
use App\Models\RealmContact;
use App\Support\ActivityLogger;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

/**
 * @property-read Community $realm
 */
class EditRealm extends Component
{
    #[Locked]
    public string $uid = '';

    #[Rule('required|min:6')]
    public string $name = '';

    #[Rule('nullable|string|max:255')]
    public string $street = '';

    #[Rule('nullable|string|max:20')]
    public string $house_number = '';

    #[Rule('nullable|string|max:20')]
    public string $postal_code = '';

    #[Rule('nullable|string|max:255')]
    public string $city = '';

    #[Rule('nullable|string|max:255')]
    public string $office_building = '';

    #[Rule('nullable|string|max:50')]
    public string $office_room = '';

    #[Rule('nullable|email|max:255')]
    public string $email = '';

    #[Rule('nullable|string|max:50')]
    public string $phone = '';

    #[Rule('nullable|url:http,https|max:255')]
    public string $website = '';

    #[Rule('nullable|string|max:255')]
    public string $bank_name = '';

    #[Rule(['nullable', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/'], as: 'IBAN')]
    public string $iban = '';

    #[Rule(['nullable', 'regex:/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/'], as: 'BIC')]
    public string $bic = '';

    private const array CONTACT_FIELDS = ['street', 'house_number', 'postal_code', 'city', 'office_building', 'office_room', 'email', 'phone', 'website', 'bank_name', 'iban', 'bic'];

    public function mount(Community $realm): void
    {
        $this->uid = $realm->getFirstAttribute('ou');
        // here is an implicit search for the realm and return 404 if not existent
        $this->name = $this->realm->description[0] ?? '';

        $contact = RealmContact::forRealm($this->uid);
        foreach (self::CONTACT_FIELDS as $field) {
            $this->{$field} = (string) $contact?->{$field};
        }
    }

    #[Computed(persist: true)]
    public function realm(): Community
    {
        return Community::findOrFailByUid($this->uid);
    }

    public function render(): View
    {
        return view('livewire.settings.edit-realm')->title(__('realms.dashboard.title', ['realm' => $this->uid]));
    }

    public function save(): RedirectResponse
    {
        // entered as printed, stored without spaces
        $this->iban = strtoupper((string) preg_replace('/\s+/', '', $this->iban));
        $this->bic = strtoupper((string) preg_replace('/\s+/', '', $this->bic));
        $this->validate();

        $r = Community::findOrFailByUid($this->uid);
        $before = $this->realmSettings($r);
        $r->description = [$this->name];
        $r->save();

        RealmContact::updateOrCreate(['realm' => $this->uid], array_map(
            fn (string $value): ?string => trim($value) === '' ? null : trim($value),
            $this->only(self::CONTACT_FIELDS),
        ));

        $details = ActivityLogger::diff($before, $this->realmSettings($r));
        if ($details !== []) {
            resolve(ActivityLogger::class)->record('realm.updated', $this->uid, $r, $details);
        }

        Flux::toast(variant: 'success', text: __('realms.edit_success', ['realm' => $this->uid]));

        return to_route('realms.dashboard', ['realm' => $this->uid]);
    }

    /** @return array<string, ?string> */
    private function realmSettings(Community $realm): array
    {
        $contact = RealmContact::forRealm($this->uid);

        return [
            'name' => $realm->getFirstAttribute('description'),
            ...array_map(fn (string $field): ?string => $contact?->getAttribute($field), array_combine(self::CONTACT_FIELDS, self::CONTACT_FIELDS)),
        ];
    }
}

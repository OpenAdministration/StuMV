<?php

namespace App\Livewire\Settings;

use App\Ldap\Community;
use App\Models\RealmContact;
use Flux\Flux;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

/**
 * @property $realm
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

    #[Rule('nullable|email|max:255')]
    public string $email = '';

    #[Rule('nullable|string|max:50')]
    public string $phone = '';

    #[Rule('nullable|url:http,https|max:255')]
    public string $website = '';

    public function mount(Community $realm): void
    {
        $this->uid = $realm->getFirstAttribute('ou');
        // here is an implicit search for the realm and return 404 if not existent
        $this->name = $this->realm->description[0] ?? '';

        $contact = RealmContact::forRealm($this->uid);
        foreach (['street', 'house_number', 'postal_code', 'city', 'email', 'phone', 'website'] as $field) {
            $this->{$field} = (string) $contact?->{$field};
        }
    }

    #[Computed(persist: true)]
    public function realm(): Community
    {
        return Community::findOrFailByUid($this->uid);
    }

    public function render()
    {
        return view('livewire.settings.edit-realm')->title(__('realms.dashboard.title', ['realm' => $this->uid]));
    }

    public function save()
    {
        $this->validate();

        $r = Community::findOrFailByUid($this->uid);
        $r->description = [$this->name];
        $r->save();

        RealmContact::updateOrCreate(['realm' => $this->uid], array_map(
            fn (string $value): ?string => trim($value) === '' ? null : trim($value),
            $this->only(['street', 'house_number', 'postal_code', 'city', 'email', 'phone', 'website']),
        ));

        Flux::toast(variant: 'success', text: __('realms.edit_success', ['realm' => $this->uid]));

        return to_route('realms.dashboard', ['realm' => $this->uid]);
    }
}

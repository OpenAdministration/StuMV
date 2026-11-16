<?php

namespace App\Livewire\Settings;

use App\Ldap\Community;
use App\Models\RealmContact;
use App\Support\CoverLetter;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Rule;
use Livewire\Component;

class EditMembershipExport extends Component
{
    #[Locked]
    public string $uid = '';

    #[Rule('nullable|string|max:100')]
    public string $letter_sender_unit = '';

    #[Rule('nullable|string|max:150')]
    public string $letter_subject = '';

    #[Rule('nullable|string|max:20000')]
    public string $letter_body = '';

    public function mount(Community $realm): void
    {
        $this->uid = $realm->getShortCode();

        $contact = RealmContact::forRealm($this->uid);
        foreach (['letter_sender_unit', 'letter_subject', 'letter_body'] as $field) {
            $this->{$field} = (string) $contact?->{$field};
        }
    }

    public function render()
    {
        return view('livewire.settings.edit-membership-export')
            ->title(__('realms.membership_export.title', ['realm' => $this->uid]));
    }

    public function save()
    {
        $this->validate();

        RealmContact::updateOrCreate(['realm' => $this->uid], [
            ...array_map(
                fn (string $value): ?string => trim($value) === '' ? null : trim($value),
                $this->only(['letter_sender_unit', 'letter_subject']),
            ),
            'letter_body' => CoverLetter::bodyIsEmpty($this->letter_body) ? null : CoverLetter::sanitizeBody($this->letter_body),
        ]);

        Flux::toast(variant: 'success', text: __('realms.membership_export.saved'));

        return to_route('settings.dashboard', ['realm' => $this->uid]);
    }
}

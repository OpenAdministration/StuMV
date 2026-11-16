<?php

namespace App\Livewire\Settings;

use App\Ldap\Community;
use App\Models\RealmContact;
use App\Support\CoverLetter;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class EditMembershipExport extends Component
{
    #[Locked]
    public string $uid = '';

    public bool $withCoverLetter = false;

    /** @var array<string, string> */
    public array $recipient = [];

    public string $headerTitle = '';

    public string $headerSubtitle = '';

    public string $senderName = '';

    public string $senderUnit = '';

    public string $senderEmail = '';

    public string $senderBuilding = '';

    public string $senderRoom = '';

    public string $senderPhone = '';

    public string $senderWebsite = '';

    public string $signerFunction = '';

    public string $subject = '';

    public string $letterBody = '';

    /** Placeholders: the export falls back to the realm's name and contact details. */
    #[Locked]
    public string $realmName = '';

    #[Locked]
    public string $contactEmail = '';

    #[Locked]
    public string $contactPhone = '';

    #[Locked]
    public string $contactBuilding = '';

    #[Locked]
    public string $contactRoom = '';

    public function mount(Community $realm): void
    {
        $this->uid = $realm->getShortCode();

        $contact = RealmContact::forRealm($this->uid);
        $this->withCoverLetter = (bool) $contact?->letter_enabled;
        $this->recipient = CoverLetter::recipient($contact->letter_recipient ?? []);
        $this->headerTitle = (string) $contact?->letter_header_title;
        $this->headerSubtitle = (string) $contact?->letter_header_subtitle;
        $this->senderName = (string) $contact?->letter_sender_name;
        $this->senderUnit = (string) $contact?->letter_sender_unit;
        $this->senderEmail = (string) $contact?->letter_sender_email;
        $this->senderBuilding = (string) $contact?->letter_sender_building;
        $this->senderRoom = (string) $contact?->letter_sender_room;
        $this->senderPhone = (string) $contact?->letter_sender_phone;
        $this->senderWebsite = (string) $contact?->letter_sender_website;
        $this->signerFunction = (string) $contact?->letter_signer_function;
        $this->subject = (string) $contact?->letter_subject;
        $this->letterBody = (string) $contact?->letter_body;
        $this->realmName = (string) $realm->getFirstAttribute('description');
        $this->contactEmail = (string) $contact?->email;
        $this->contactPhone = (string) $contact?->phone;
        $this->contactBuilding = (string) $contact?->office_building;
        $this->contactRoom = (string) $contact?->office_room;
    }

    public function render()
    {
        return view('livewire.settings.edit-membership-export')
            ->title(__('realms.membership_export.title', ['realm' => $this->uid]));
    }

    public function save()
    {
        $this->validate(
            [
                'withCoverLetter' => ['boolean'],
                ...CoverLetter::recipientRules('recipient'),
                'headerTitle' => ['nullable', 'string', 'max:150'],
                'headerSubtitle' => ['nullable', 'string', 'max:150'],
                'senderName' => ['nullable', 'string', 'max:150'],
                'senderUnit' => ['nullable', 'string', 'max:100'],
                'senderEmail' => ['nullable', 'email', 'max:255'],
                'senderBuilding' => ['nullable', 'string', 'max:255'],
                'senderRoom' => ['nullable', 'string', 'max:50'],
                'senderPhone' => ['nullable', 'string', 'max:50'],
                'senderWebsite' => ['nullable', 'url:http,https', 'max:255'],
                'signerFunction' => ['nullable', 'string', 'max:100'],
                'subject' => ['nullable', 'string', 'max:150'],
                'letterBody' => ['nullable', 'string', 'max:20000'],
            ],
            attributes: [
                'headerTitle' => __('profile.cover_letter_header_title'),
                'headerSubtitle' => __('profile.cover_letter_header_subtitle'),
                'senderName' => __('profile.cover_letter_sender_name'),
                'senderUnit' => __('profile.cover_letter_sender_unit'),
                'senderEmail' => __('profile.cover_letter_sender_email'),
                'senderBuilding' => __('realms.contact_office_building'),
                'senderRoom' => __('realms.contact_office_room'),
                'senderPhone' => __('profile.cover_letter_sender_phone'),
                'senderWebsite' => __('profile.cover_letter_sender_website'),
                'signerFunction' => __('profile.cover_letter_signer_function'),
                'subject' => __('profile.cover_letter_subject'),
                'letterBody' => __('profile.cover_letter_body'),
            ],
        );

        $clean = fn (string $value): ?string => trim($value) === '' ? null : trim($value);
        $recipient = array_filter(array_map($clean, CoverLetter::recipient($this->recipient)));

        RealmContact::updateOrCreate(['realm' => $this->uid], [
            'letter_enabled' => $this->withCoverLetter,
            'letter_recipient' => $recipient ?: null,
            'letter_header_title' => $clean($this->headerTitle),
            'letter_header_subtitle' => $clean($this->headerSubtitle),
            'letter_sender_name' => $clean($this->senderName),
            'letter_sender_unit' => $clean($this->senderUnit),
            'letter_sender_email' => $clean($this->senderEmail),
            'letter_sender_building' => $clean($this->senderBuilding),
            'letter_sender_room' => $clean($this->senderRoom),
            'letter_sender_phone' => $clean($this->senderPhone),
            'letter_sender_website' => $clean($this->senderWebsite),
            'letter_signer_function' => $clean($this->signerFunction),
            'letter_subject' => $clean($this->subject),
            'letter_body' => CoverLetter::bodyIsEmpty($this->letterBody) ? null : CoverLetter::sanitizeBody($this->letterBody),
        ]);

        Flux::toast(variant: 'success', text: __('realms.membership_export.saved'));

        return to_route('settings.dashboard', ['realm' => $this->uid]);
    }
}

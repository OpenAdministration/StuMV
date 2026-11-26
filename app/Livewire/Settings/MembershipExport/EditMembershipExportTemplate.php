<?php

namespace App\Livewire\Settings\MembershipExport;

use App\Enums\ExportDateFormat;
use App\Enums\LetterRecipient;
use App\Enums\LetterSender;
use App\Ldap\Community;
use App\Models\MembershipExportTemplate;
use App\Models\RealmContact;
use App\Support\CoverLetter;
use App\Support\LetterPlaceholders;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Creates a template, or edits one when given. */
class EditMembershipExportTemplate extends Component
{
    #[Locked]
    public string $uid = '';

    #[Locked]
    public ?int $templateId = null;

    public string $name = '';

    public bool $withLetter = false;

    public string $sender = LetterSender::Realm->value;

    public string $dateFormat = ExportDateFormat::Iso->value;

    public string $recipientType = LetterRecipient::Other->value;

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

    public function mount(Community $realm, ?MembershipExportTemplate $template = null): void
    {
        $this->uid = $realm->getShortCode();
        $this->recipient = CoverLetter::recipient();

        if ($template?->exists) {
            abort_unless($template->realm === $this->uid, 404);
            $this->templateId = $template->id;
            $this->name = $template->name;
            $this->withLetter = $template->with_letter;
            $this->sender = $template->sender->value;
            $this->dateFormat = $template->date_format->value;
            $this->recipientType = $template->recipient_type->value;
            $this->recipient = CoverLetter::recipient($template->recipient ?? []);
            $this->headerTitle = (string) $template->header_title;
            $this->headerSubtitle = (string) $template->header_subtitle;
            $this->senderName = (string) $template->sender_name;
            $this->senderUnit = (string) $template->sender_unit;
            $this->senderEmail = (string) $template->sender_email;
            $this->senderBuilding = (string) $template->sender_building;
            $this->senderRoom = (string) $template->sender_room;
            $this->senderPhone = (string) $template->sender_phone;
            $this->senderWebsite = (string) $template->sender_website;
            $this->subject = (string) $template->subject;
            $this->letterBody = (string) $template->body;
        }

        $contact = RealmContact::forRealm($this->uid);
        $this->realmName = (string) $realm->getFirstAttribute('description');
        $this->contactEmail = (string) $contact?->email;
        $this->contactPhone = (string) $contact?->phone;
        $this->contactBuilding = (string) $contact?->office_building;
        $this->contactRoom = (string) $contact?->office_room;
    }

    public function render()
    {
        return view('livewire.settings.membership-export.edit-membership-export-template', [
            'dateFormats' => ExportDateFormat::cases(),
            'senders' => LetterSender::cases(),
            'recipientTypes' => LetterRecipient::cases(),
            'placeholders' => LetterPlaceholders::labels(),
        ])->title(__('realms.membership_export.title', ['realm' => $this->uid]));
    }

    public function save()
    {
        $letter = 'exclude_unless:withLetter,true';
        $otherRecipient = 'exclude_unless:recipientType,'.LetterRecipient::Other->value;
        $knownPlaceholders = function (string $attribute, mixed $value, \Closure $fail): void {
            $unknown = LetterPlaceholders::unknown((string) $value);
            if ($attribute === 'subject' && str_contains((string) $value, '[signature')) {
                $unknown[] = LetterPlaceholders::SIGNATURE;
            }

            if ($unknown !== []) {
                $fail(__('realms.membership_export.placeholder_unknown', ['placeholders' => implode(', ', $unknown)]));
            } elseif (LetterPlaceholders::signatureMisplaced(CoverLetter::sanitizeBody((string) $value))) {
                $fail(__('realms.membership_export.placeholder_signature_misplaced', ['placeholder' => LetterPlaceholders::SIGNATURE]));
            }
        };

        $this->validate(
            [
                'name' => ['required', 'string', 'max:100'],
                'withLetter' => ['boolean'],
                'dateFormat' => ['required', Rule::enum(ExportDateFormat::class)],
                'sender' => ['required', Rule::enum(LetterSender::class)],
                'recipientType' => [$letter, 'required', Rule::enum(LetterRecipient::class)],
                ...CoverLetter::recipientRules('recipient', [$letter, $otherRecipient]),
                'headerTitle' => [$letter, 'nullable', 'string', 'max:150'],
                'headerSubtitle' => [$letter, 'nullable', 'string', 'max:150'],
                'senderName' => [$letter, 'nullable', 'string', 'max:150'],
                'senderUnit' => [$letter, 'nullable', 'string', 'max:100'],
                'senderEmail' => [$letter, 'nullable', 'email', 'max:255'],
                'senderBuilding' => [$letter, 'nullable', 'string', 'max:255'],
                'senderRoom' => [$letter, 'nullable', 'string', 'max:50'],
                'senderPhone' => [$letter, 'nullable', 'string', 'max:50'],
                'senderWebsite' => [$letter, 'nullable', 'url:http,https', 'max:255'],
                'subject' => [$letter, 'nullable', 'string', 'max:150', $knownPlaceholders],
                'letterBody' => [
                    $letter, 'required', 'string', 'max:20000', $knownPlaceholders,
                    // the editor submits <p></p> when empty
                    fn (string $attribute, mixed $value, \Closure $fail) => CoverLetter::bodyIsEmpty((string) $value)
                        ? $fail(__('validation.required', ['attribute' => __('profile.cover_letter_body')]))
                        : null,
                ],
            ],
            attributes: [
                'name' => __('realms.membership_export.name'),
                'dateFormat' => __('realms.membership_export.date_format'),
                'sender' => __('profile.cover_letter_sender'),
                'recipientType' => __('profile.cover_letter_recipient'),
                'headerTitle' => __('profile.cover_letter_header_title'),
                'headerSubtitle' => __('profile.cover_letter_header_subtitle'),
                'senderName' => __('profile.cover_letter_sender_name'),
                'senderUnit' => __('profile.cover_letter_sender_unit'),
                'senderEmail' => __('profile.cover_letter_sender_email'),
                'senderBuilding' => __('realms.contact_office_building'),
                'senderRoom' => __('realms.contact_office_room'),
                'senderPhone' => __('profile.cover_letter_sender_phone'),
                'senderWebsite' => __('profile.cover_letter_sender_website'),
                'subject' => __('profile.cover_letter_subject'),
                'letterBody' => __('profile.cover_letter_body'),
            ],
        );

        $clean = fn (string $value): ?string => trim($value) === '' ? null : trim($value);
        $recipient = array_filter(array_map($clean, CoverLetter::recipient($this->recipient)));

        $values = [
            'name' => trim($this->name),
            'with_letter' => $this->withLetter,
            'sender' => $this->sender,
            'date_format' => $this->dateFormat,
            'recipient_type' => $this->recipientType,
            'recipient' => $this->recipientType === LetterRecipient::Other->value && $recipient ? $recipient : null,
            'header_title' => $clean($this->headerTitle),
            'header_subtitle' => $clean($this->headerSubtitle),
            'sender_name' => $clean($this->senderName),
            'sender_unit' => $clean($this->senderUnit),
            'sender_email' => $clean($this->senderEmail),
            'sender_building' => $clean($this->senderBuilding),
            'sender_room' => $clean($this->senderRoom),
            'sender_phone' => $clean($this->senderPhone),
            'sender_website' => $clean($this->senderWebsite),
            'subject' => $clean($this->subject),
            'body' => CoverLetter::bodyIsEmpty($this->letterBody) ? null : CoverLetter::sanitizeBody($this->letterBody),
        ];

        if ($this->templateId !== null) {
            $template = MembershipExportTemplate::where('realm', $this->uid)->findOrFail($this->templateId);
            $template->update($values);
            $template->recordAction('updated');
        } else {
            MembershipExportTemplate::create([...$values, 'realm' => $this->uid])->recordAction('created');
        }

        Flux::toast(variant: 'success', text: __('realms.membership_export.saved'));

        return to_route('realms.membership-export', ['realm' => $this->uid]);
    }
}

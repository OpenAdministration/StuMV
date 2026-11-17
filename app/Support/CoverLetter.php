<?php

namespace App\Support;

use App\Enums\LetterSender;
use App\Ldap\Community;
use App\Ldap\User;
use App\Models\MembershipExportTemplate;
use App\Models\RealmBranding;
use App\Models\RealmContact;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/** A DIN 5008 cover letter put in front of the membership certificate. */
final readonly class CoverLetter
{
    /** The separate fields a recipient address is entered in. */
    public const array RECIPIENT_FIELDS = ['organisation', 'unit', 'name', 'street', 'house_number', 'postal_code', 'city', 'country'];

    public function __construct(
        public string $senderName,
        public ?RealmContact $contact,
        public string $recipient,
        public string $body,
        /** Path of the realm logo on the public disk, if there is one. */
        public ?string $logoPath = null,
        /** The part of the realm writing, e.g. its speakers or a department. */
        public ?string $senderUnit = null,
        /** Replace the realm's office for this letter. */
        public ?string $building = null,
        public ?string $room = null,
        /** Replaces the realm's email address for this letter. */
        public ?string $email = null,
        /** Replaces the realm's phone number for this letter. */
        public ?string $phone = null,
        /** A website of the sending unit; the info block shows none without it. */
        public ?string $website = null,
        public ?string $subject = null,
        /** Letterhead above the rule, both optional; without either the sender name is shown. */
        public ?string $headerTitle = null,
        public ?string $headerSubtitle = null,
        /** Sent by a person: their name right-aligned, no info block and no contact details in the footer. */
        public bool $fromPerson = false,
    ) {}

    /**
     * The letter a template describes, sent by the realm or by $sender.
     * $person is the one whose memberships are exported.
     */
    public static function fromTemplate(MembershipExportTemplate $template, Community $community, User $person, User $sender): self
    {
        $realmName = (string) $community->getFirstAttribute('description');
        $values = LetterPlaceholders::values($person, $sender, $realmName, $template->date_format);
        $shared = [
            'recipient' => self::recipientAddress(self::recipient($template->recipient ?? [])),
            'body' => LetterPlaceholders::replace(self::sanitizeBody((string) $template->body), $values, html: true),
            'subject' => $template->subject !== null ? LetterPlaceholders::replace($template->subject, $values, html: false) : null,
        ];

        if ($template->sender === LetterSender::User) {
            $name = (string) $sender->getFirstAttribute('cn');

            // a person's letter carries only their name and address
            return new self(
                ...$shared,
                senderName: $name,
                // never saved, it only carries the profile address to the return address and footer
                contact: new RealmContact([
                    'street' => $sender->getFirstAttribute('street'),
                    'postal_code' => $sender->getFirstAttribute('postalCode'),
                    'city' => $sender->getFirstAttribute('l'),
                ]),
                headerTitle: $name,
                fromPerson: true,
            );
        }

        $realm = $community->getShortCode();
        $contact = RealmContact::forRealm($realm);
        $logo = RealmBranding::forRealm($realm)?->logo_id;

        return new self(
            ...$shared,
            senderName: $template->sender_name ?? $realmName,
            contact: $contact,
            logoPath: $logo ? 'realm-branding/'.$logo : null,
            building: $template->sender_building ?? $contact?->office_building,
            room: $template->sender_room ?? $contact?->office_room,
            email: $template->sender_email ?? $contact?->email,
            phone: $template->sender_phone ?? $contact?->phone,
            website: $template->sender_website,
            senderUnit: $template->sender_unit,
            headerTitle: $template->header_title,
            headerSubtitle: $template->header_subtitle,
        );
    }

    /** Editor HTML reduced to what the letter can print: paragraphs, emphasis, lists and rules. */
    public static function sanitizeBody(string $html): string
    {
        $config = new HtmlSanitizerConfig;
        foreach (['p', 'br', 'hr', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li'] as $element) {
            $config = $config->allowElement($element);
        }

        // links make no sense on paper, but their text stays
        foreach (['a', 'span'] as $element) {
            $config = $config->blockElement($element);
        }

        return new HtmlSanitizer($config)->sanitize($html);
    }

    public static function bodyIsEmpty(string $html): bool
    {
        return trim(html_entity_decode(strip_tags(self::sanitizeBody($html))), " \t\n\r\0\x0B\u{A0}") === '';
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    public static function recipient(array $values = []): array
    {
        return array_map(
            fn (string $field): string => (string) ($values[$field] ?? ''),
            array_combine(self::RECIPIENT_FIELDS, self::RECIPIENT_FIELDS),
        );
    }

    /**
     * Validation rules for the recipient fields under $prefix.
     *
     * @param  list<string>  $before  rules every field starts with
     * @return array<string, list<mixed>>
     */
    public static function recipientRules(string $prefix, array $before = []): array
    {
        $rules = [];
        foreach (self::RECIPIENT_FIELDS as $field) {
            $max = in_array($field, ['house_number', 'postal_code'], true) ? 20 : 100;
            $rules[$prefix.'.'.$field] = [...$before, 'nullable', 'string', 'max:'.$max];
        }

        return $rules;
    }

    /**
     * The address lines in DIN 5008 order; a country goes last, in capitals.
     *
     * @param  array<string, string>  $recipient
     */
    public static function recipientAddress(array $recipient): string
    {
        $field = fn (string $key): string => trim($recipient[$key] ?? '');

        return implode("\n", array_filter([
            $field('organisation'),
            $field('unit'),
            $field('name'),
            trim($field('street').' '.$field('house_number')),
            trim($field('postal_code').' '.$field('city')),
            mb_strtoupper($field('country')),
        ]));
    }
}

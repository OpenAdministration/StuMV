<?php

namespace App\Support;

use App\Enums\ExportDateFormat;
use App\Ldap\User;
use Illuminate\Support\Facades\Date;

/** [person.name] and the like in the subject and text of a cover letter. */
final class LetterPlaceholders
{
    /** Placeholders for the exported person, by the LDAP attribute they read. */
    private const array PERSON = [
        'person.name' => 'cn',
        'person.email' => 'mail',
        'person.course' => 'description',
        'person.street' => 'street',
        'person.postal_code' => 'postalCode',
        'person.city' => 'l',
        'person.phone' => 'telephoneNumber',
    ];

    private const array OTHER = ['sender.name', 'realm.name', 'today'];

    private const string PATTERN = '/\[([a-z_]+(?:\.[a-z_]+)?)\]/';

    /** Room to sign and a line, only in the text and as a paragraph of its own. */
    public const string SIGNATURE = '[signature]';

    /** Lines a signature can have below its line, in this order. */
    private const array SIGNATURE_LINES = ['name', 'function'];

    /** [signature;name=…;function=…] alone in a paragraph; the parameters are checked once decoded. */
    private const string SIGNATURE_PARAGRAPH = '#<p>\s*\[signature([^<]*)\]\s*</p>#';

    private const string SIGNATURE_PARAMETERS = '/^(;(name|function)=[^;]*)*$/';

    /** @return array<string, string> placeholder => label */
    public static function labels(): array
    {
        $labels = [];
        foreach ([...array_keys(self::PERSON), ...self::OTHER] as $key) {
            $labels['['.$key.']'] = __('realms.membership_export.placeholder_'.str_replace('.', '_', $key));
        }

        $labels[self::SIGNATURE] = __('realms.membership_export.placeholder_signature');

        return $labels;
    }

    /** @return array<string, string> */
    public static function values(User $person, User $sender, string $realmName, ExportDateFormat $dateFormat): array
    {
        return [
            ...array_map(fn (string $attribute): string => (string) $person->getFirstAttribute($attribute), self::PERSON),
            'sender.name' => (string) $sender->getFirstAttribute('cn'),
            'realm.name' => $realmName,
            'today' => $dateFormat->format(Date::today()),
        ];
    }

    /**
     * @param  array<string, string>  $values
     */
    public static function replace(string $text, array $values, bool $html): string
    {
        return (string) preg_replace_callback(
            self::PATTERN,
            fn (array $match): string => array_key_exists($match[1], $values)
                ? ($html ? e($values[$match[1]]) : $values[$match[1]])
                : $match[0],
            $text,
        );
    }

    /** @return list<string> placeholders in the text that are not known */
    public static function unknown(string $text): array
    {
        preg_match_all(self::PATTERN, $text, $matches);

        return array_values(array_unique(array_diff($matches[0], array_keys(self::labels()))));
    }

    /** Whether a signature placeholder is malformed or shares its paragraph with something else. */
    public static function signatureMisplaced(string $html): bool
    {
        preg_match_all(self::SIGNATURE_PARAGRAPH, $html, $matches);
        $valid = array_filter($matches[1], fn (string $parameters): bool => self::signatureLines($parameters) !== null);

        return substr_count($html, '[signature') !== count($valid);
    }

    /**
     * The text split where a signature goes: each part with the lines below the signature that follows it, if one does.
     *
     * @return list<array{0: string, 1: list<string>|null}>
     */
    public static function splitAtSignatures(string $html): array
    {
        $pieces = preg_split(self::SIGNATURE_PARAGRAPH, $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];

        return array_map(
            fn (array $chunk): array => [$chunk[0], isset($chunk[1]) ? self::signatureLines($chunk[1]) ?? [] : null],
            array_chunk($pieces, 2),
        );
    }

    /** @return list<string>|null null when the parameters are not valid */
    private static function signatureLines(string $parameters): ?array
    {
        $parameters = html_entity_decode($parameters, ENT_QUOTES | ENT_HTML5);
        if (! preg_match(self::SIGNATURE_PARAMETERS, $parameters)) {
            return null;
        }

        $lines = array_fill_keys(self::SIGNATURE_LINES, '');
        foreach (array_filter(explode(';', $parameters)) as $parameter) {
            [$key, $value] = explode('=', $parameter, 2);
            $lines[$key] = trim($value);
        }

        return array_values(array_filter($lines, fn (string $line): bool => $line !== ''));
    }
}

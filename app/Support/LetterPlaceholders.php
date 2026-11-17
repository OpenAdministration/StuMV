<?php

namespace App\Support;

use App\Enums\ExportDateFormat;
use App\Ldap\User;
use Illuminate\Support\Facades\Date;

/** [person.first_name] and the like in the subject and text of a cover letter. */
final class LetterPlaceholders
{
    /** Placeholders for the exported person, by the LDAP attribute they read. */
    private const array PERSON = [
        'person.name' => 'cn',
        'person.first_name' => 'givenName',
        'person.last_name' => 'sn',
        'person.username' => 'uid',
        'person.email' => 'mail',
        'person.course' => 'description',
        'person.street' => 'street',
        'person.postal_code' => 'postalCode',
        'person.city' => 'l',
        'person.phone' => 'telephoneNumber',
    ];

    private const array OTHER = ['realm.name', 'today'];

    private const string PATTERN = '/\[([a-z_]+(?:\.[a-z_]+)?)\]/';

    /** @return array<string, string> placeholder => label */
    public static function labels(): array
    {
        $labels = [];
        foreach ([...array_keys(self::PERSON), ...self::OTHER] as $key) {
            $labels['['.$key.']'] = __('realms.membership_export.placeholder_'.str_replace('.', '_', $key));
        }

        return $labels;
    }

    /** @return array<string, string> */
    public static function values(User $person, string $realmName, ExportDateFormat $dateFormat): array
    {
        return [
            ...array_map(fn (string $attribute): string => (string) $person->getFirstAttribute($attribute), self::PERSON),
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
}

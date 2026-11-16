<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RealmContact extends Model
{
    protected $table = 'realm_contacts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'realm',
        'street',
        'house_number',
        'postal_code',
        'city',
        'office_building',
        'office_room',
        'email',
        'phone',
        'website',
        'bank_name',
        'iban',
        'bic',
        'letter_enabled',
        'letter_recipient',
        'letter_sender_name',
        'letter_sender_unit',
        'letter_sender_email',
        'letter_sender_phone',
        'letter_signer_function',
        'letter_subject',
        'letter_body',
    ];

    protected function casts(): array
    {
        return [
            'letter_enabled' => 'boolean',
            'letter_recipient' => 'array',
        ];
    }

    public static function forRealm(string $realmUid): ?self
    {
        return static::where('realm', $realmUid)->first();
    }

    public function streetLine(): string
    {
        return trim($this->street.' '.$this->house_number);
    }

    public function cityLine(): string
    {
        return trim($this->postal_code.' '.$this->city);
    }

    /** The IBAN as printed: in groups of four. */
    public function ibanLabel(): string
    {
        return trim(chunk_split((string) $this->iban, 4, ' '));
    }

    /** The website as printed: without scheme and trailing slash. */
    public function websiteLabel(): string
    {
        return rtrim((string) preg_replace('#^https?://#i', '', (string) $this->website), '/');
    }
}

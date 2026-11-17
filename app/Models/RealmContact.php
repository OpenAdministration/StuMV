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
    ];

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
        return self::urlLabel((string) $this->website);
    }

    public static function urlLabel(string $url): string
    {
        return rtrim((string) preg_replace('#^https?://#i', '', $url), '/');
    }
}

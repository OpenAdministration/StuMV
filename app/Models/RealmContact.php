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
        'email',
        'phone',
        'website',
        'letter_sender_unit',
        'letter_subject',
        'letter_body',
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

    /** The website as printed: without scheme and trailing slash. */
    public function websiteLabel(): string
    {
        return rtrim((string) preg_replace('#^https?://#i', '', (string) $this->website), '/');
    }
}

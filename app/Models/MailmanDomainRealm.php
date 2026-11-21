<?php

namespace App\Models;

use App\Support\MailmanClient;
use Illuminate\Database\Eloquent\Model;

/**
 * A Mailman domain a realm may create lists under.
 *
 * @property string $mail_host
 * @property string $realm
 */
class MailmanDomainRealm extends Model
{
    protected $table = 'mailman_domain_realms';

    protected $fillable = ['mail_host', 'realm'];

    /**
     * @return array<int, string>
     */
    public static function allowedFor(string $realm): array
    {
        return static::where('realm', $realm)->orderBy('mail_host')->pluck('mail_host')->all();
    }

    public static function creationAllowedFor(string $realm): bool
    {
        return MailmanClient::listCreationEnabled() && static::where('realm', $realm)->exists();
    }
}

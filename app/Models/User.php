<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;

class User extends Authenticatable implements LdapAuthenticatable, MustVerifyEmail
{
    use AuthenticatesWithLdap;
    use HasApiTokens, HasFactory, Notifiable;

    protected $table = 'user';

    /***
     * @inheritDoc
     */
    public function getLdapGuidColumn(): string
    {
        // openLdap specific
        return 'uid';
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'full_name',
        'username',
        'email',
        'password',
        'realm',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return \App\Ldap\User Returns the equivalent LDAP user
     */
    public function ldap(): \App\Ldap\User
    {
        // $this->uid actually stores the LDAP entryUUID (see getLdapGuidColumn()
        // above), not the uid attribute - a GUID lookup anchors to this specific
        // physical entry, unlike a username search which is ambiguous once the
        // same username can belong to independent accounts in different realms.
        return \App\Ldap\User::query()->findByGuidOrFail($this->uid);
    }

    /**
     * Same as ldap(), but returns null instead of aborting when this account
     * has no matching LDAP entry (e.g. mid-registration/email-verification).
     */
    public function ldapOrNull(): ?\App\Ldap\User
    {
        return $this->uid ? \App\Ldap\User::query()->findByGuid($this->uid) : null;
    }

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }
}

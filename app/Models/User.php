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
        return \App\Ldap\User::findOrFailByUsername($this->username);
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

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
    use HasApiTokens, HasFactory, Notifiable;
    use AuthenticatesWithLdap;

    protected $table = 'user';

    /***
     * @inheritDoc
     */
    public function getLdapGuidColumn() : string
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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * @return \App\Ldap\User Returns the equivalent LDAP user
     */
    public function ldap() : \App\Ldap\User
    {
        return \App\Ldap\User::findOrFailByUsername($this->username);
    }

}

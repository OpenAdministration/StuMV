<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;

class User extends Authenticatable implements LdapAuthenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    use AuthenticatesWithLdap;

    protected string $guidKey = 'uid';

    public static array $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'inetOrgPerson',
    ];

    protected $table = 'user';

    public function getLdapGuidColumn() : string
    {
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
        'is_superuser',
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
        'is_superuser' => 'boolean',
    ];

    /**
     * @return BelongsToMany
     */
    public function admin_realms(): Relation
    {
        return $this->belongsToMany(Realm::class, 'realm_admin_relation');
    }

    /**
     * @return BelongsToMany
     */
    public function realms(): Relation
    {
        return $this->belongsToMany(Realm::class, 'realm_user_relation');
    }

    /**
     * @return HasMany
     */
    public function roles(): Relation
    {
        return $this->hasMany(RoleUserRelation::class, 'user_id');
    }
}

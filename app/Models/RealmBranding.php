<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RealmBranding extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'realm_branding';

    /**
     * @var array
     */
    protected $fillable = [
        'realm',
        'logo_id',
        'background_id',
    ];

    public static function forRealm(?string $realmUid): ?self
    {
        if (! $realmUid) {
            return null;
        }

        return static::where('realm', $realmUid)->first();
    }
}

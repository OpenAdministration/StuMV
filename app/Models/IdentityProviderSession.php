<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Correlates a Laravel session with the external identity provider "sub"
 * claim it was established under - the only way an inbound OIDC
 * back-channel logout_token (which identifies the user solely by "sub",
 * never by our own session id) can be mapped back to the StuMV session(s)
 * that need ending. See OidcLoginController::backChannelLogout().
 */
class IdentityProviderSession extends Model
{
    protected $fillable = [
        'provider_id',
        'external_sub',
        'session_id',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(RealmIdentityProvider::class, 'provider_id');
    }
}

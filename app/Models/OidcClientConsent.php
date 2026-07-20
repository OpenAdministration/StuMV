<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OidcClientConsent extends Model
{
    protected $table = 'oauth_client_consents';

    protected $fillable = [
        'client_id',
        'user_id',
        'scopes',
    ];

    protected function casts(): array
    {
        return [
            'scopes' => 'array',
        ];
    }

    #[\Override]
    public function getConnectionName(): ?string
    {
        return $this->connection ?? config('passport.connection');
    }
}

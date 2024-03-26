<?php

namespace App\Models;

use Laravel\Passport\Client;

class PassportClient extends Client
{
    /**
     * Determine if the client should skip the authorization prompt.
     * Here no App needs a confirmation dialog after login
     */
    public function skipsAuthorization(): bool
    {
        return true;
    }

}

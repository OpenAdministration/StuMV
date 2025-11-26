<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SocialiteUser extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $ldapUser = \App\Ldap\User::findOrFailByUsername($user->username);
        return response()->json([
            'id' => $user->uid, // not ldap uid, but uuid
            'nickname' => $user->username, // socialite expected claim
            'username' => $user->username, // filled with ldap uid
            'name' => $user->full_name, // cn
            'email' => $user->email,
            'picture' => $ldapUser->getFirstAttribute('jpegPhoto'),
            'iban' => null,
            'address' => json_encode([
                'street_address' => $ldapUser->getFirstAttribute('street'),
                'postal_code' => $ldapUser->getFirstAttribute('postalCode'),
                'locality' => $ldapUser->getFirstAttribute('l'),
            ]),
            'phone_number' => $ldapUser->getFirstAttribute('telephoneNumber'),
        ]);
    }
}

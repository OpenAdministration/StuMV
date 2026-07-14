<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Ldap\User;
use App\Models\ProfilePicture;
use Illuminate\Http\Request;

class SocialiteUser extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
        $ldapUser = User::findOrFailByUsername($user->username);
        $picture = ProfilePicture::where('user', $user->username)->first();

        return response()->json([
            'id' => $user->uid, // not ldap uid, but uuid
            'nickname' => $user->username, // socialite expected claim
            'username' => $user->username, // filled with ldap uid
            'name' => $user->full_name, // cn
            'email' => $user->email,
            // Public URL to the stored avatar (same shape as Directory\Users);
            // the raw jpegPhoto is base64 and breaks response()->json().
            'picture' => $picture ? asset('storage/avatars/'.$picture->file_id.'.jpg') : null,
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

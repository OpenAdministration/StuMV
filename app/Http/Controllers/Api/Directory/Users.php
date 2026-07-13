<?php

namespace App\Http\Controllers\Api\Directory;

use App\Http\Controllers\Api\Directory\Concerns\AuthorizesDirectoryClient;
use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\ProfilePicture;
use Illuminate\Http\Request;

class Users extends Controller
{
    use AuthorizesDirectoryClient;

    public function show(Request $request, Community $uid, string $username)
    {
        $this->authorizeClientForCommunity($uid);

        $user = LdapUser::findByUsername($username) ?? abort(404);

        // Keep the lookup realm-bound: only expose users who are actually a
        // member of this community, not any LDAP account anywhere.
        abort_unless($uid->membersGroup()->members()->exists($user), 404);

        $picture = ProfilePicture::where('user', $username)->first();

        return response()->json([
            'uid' => $user->getFirstAttribute('uid'),
            'name' => $user->getFirstAttribute('cn'),
            'given_name' => $user->getFirstAttribute('givenName'),
            'family_name' => $user->getFirstAttribute('sn'),
            'picture' => $picture ? asset('storage/avatars/'.$picture->file_id.'.jpg') : null,
        ]);
    }
}

<?php

namespace App\Support;

use App\Ldap\Committee;
use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\IdentityProviderGroupGrant;
use App\Models\Invitation;
use App\Models\ProfilePicture;
use App\Models\RealmIdentityProvider;
use App\Models\RoleMembership;
use App\Models\User;
use App\Models\UserAdditionalEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Passport\AuthCode;
use Laravel\Passport\DeviceCode;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token as PassportToken;
use LdapRecord\Models\OpenLDAP\Entry;

/** Deletes one account of a realm: the LDAP entry and every DB row and file of it. */
class AccountPurger
{
    public static function purge(Community $community, string $username): void
    {
        $realm = $community->getShortCode();
        $ldap = LdapUser::query()->in($community->peopleDn())->where('uid', '=', $username)->first();
        $email = $ldap?->getFirstAttribute('mail');

        DB::transaction(function () use ($realm, $username, $email): void {
            // username columns have no realm of their own, committee_dn scopes them
            RoleMembership::where('username', $username)
                ->where('committee_dn', 'like', '%'.Committee::dnRootResolved($realm))
                ->delete();
            UserAdditionalEmail::where('username', $username)->where('realm', $realm)->delete();
            IdentityProviderGroupGrant::where('username', $username)
                ->whereIn('provider_id', RealmIdentityProvider::where('realm', $realm)->select('id'))
                ->delete();
            Invitation::where('invited_by_username', $username)->where('realm', $realm)->update(['invited_by_username' => null]);

            $files = [];
            foreach (ProfilePicture::where('user', $username)->where('realm', $realm)->get() as $picture) {
                $files[] = 'avatars/'.$picture->file_id.'.webp';
                $picture->delete();
            }
            DB::afterCommit(fn () => Storage::disk('public')->delete($files));

            $userIds = User::where('username', $username)->where('realm', $realm)->pluck('id');
            $tokenIds = PassportToken::whereIn('user_id', $userIds)->pluck('id');
            RefreshToken::whereIn('access_token_id', $tokenIds)->delete();
            PassportToken::whereIn('id', $tokenIds)->delete();
            AuthCode::whereIn('user_id', $userIds)->delete();
            DeviceCode::whereIn('user_id', $userIds)->delete();
            DB::table(config('session.table', 'sessions'))->whereIn('user_id', $userIds)->delete();
            User::whereIn('id', $userIds)->delete();

            if ($email) {
                DB::table('password_reset_tokens')->where('email', $email)->where('realm', $realm)->delete();
            }
        });

        if ($ldap === null) {
            return;
        }

        // groups, admins, moderators etc. - keep the placeholder an otherwise empty entry needs
        foreach (Entry::query()->where('uniqueMember', '=', $ldap->getDn())->get() as $entry) {
            $members = array_values(array_diff($entry->getAttribute('uniqueMember') ?? [], [$ldap->getDn()]));
            $entry->replaceAttribute('uniqueMember', $members === [] ? [''] : $members);
            $entry->save();
        }

        // last, so a failed cleanup leaves the account usable
        $ldap->delete();
    }
}

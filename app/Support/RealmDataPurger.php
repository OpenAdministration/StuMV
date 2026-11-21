<?php

namespace App\Support;

use App\Ldap\Group;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\Invitation;
use App\Models\MailmanDomainRealm;
use App\Models\MembershipExportTemplate;
use App\Models\PassportClient;
use App\Models\ProfilePicture;
use App\Models\RealmBranding;
use App\Models\RealmContact;
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

/**
 * Deletes every DB-side row (and file) scoped to a realm - none of it is
 * covered by Community::delete(recursive: true), which only tears down the
 * LDAP subtree. Realms are plain string tags here (no FK ties this data to
 * a realm), so each table has to be swept explicitly.
 */
class RealmDataPurger
{
    public static function purge(string $uid): void
    {
        DB::transaction(function () use ($uid): void {
            RoleMembership::where('realm', $uid)->delete();

            // GroupMembership has no realm column of its own - group_dn
            // encodes it (see Group::dnRoot()), so a mapping belongs to
            // this realm exactly when its group lives under this realm's
            // Groups OU.
            GroupMembership::where('group_dn', 'like', '%,'.Group::dnRoot($uid))->delete();

            GroupMailmanList::where('realm', $uid)->delete();
            MailmanDomainRealm::where('realm', $uid)->delete();

            // invitation_role_selections cascade via their FK.
            Invitation::where('realm', $uid)->delete();

            UserAdditionalEmail::where('realm', $uid)->delete();

            // Files go only once the surrounding transaction has committed.
            $files = [];

            foreach (ProfilePicture::where('realm', $uid)->get() as $picture) {
                $files[] = 'avatars/'.$picture->file_id.'.webp';
                $picture->delete();
            }

            if ($branding = RealmBranding::forRealm($uid)) {
                foreach (['logo_id', 'background_id'] as $column) {
                    if ($branding->{$column}) {
                        $files[] = 'realm-branding/'.$branding->{$column};
                    }
                }
                $branding->delete();
            }

            DB::afterCommit(fn () => Storage::disk('public')->delete($files));

            RealmContact::where('realm', $uid)->delete();
            MembershipExportTemplate::where('realm', $uid)->delete();

            // Child identity_provider_role_mappings rows cascade via their own
            // FK (see 2026_07_20_000006_create_realm_identity_providers_table).
            RealmIdentityProvider::where('realm', $uid)->delete();

            // None of the oauth_* tables cascade, so tokens, refresh tokens and
            // codes of the realm's clients and users are swept explicitly.
            $clientIds = PassportClient::where('community_uid', $uid)->pluck('id');
            $userIds = User::where('realm', $uid)->pluck('id');

            $tokenIds = PassportToken::whereIn('client_id', $clientIds)->orWhereIn('user_id', $userIds)->pluck('id');
            RefreshToken::whereIn('access_token_id', $tokenIds)->delete();
            PassportToken::whereIn('id', $tokenIds)->delete();
            AuthCode::whereIn('client_id', $clientIds)->orWhereIn('user_id', $userIds)->delete();
            DeviceCode::whereIn('client_id', $clientIds)->orWhereIn('user_id', $userIds)->delete();
            PassportClient::whereIn('id', $clientIds)->delete();

            DB::table(config('session.table', 'sessions'))->whereIn('user_id', $userIds)->delete();
            User::whereIn('id', $userIds)->delete();

            DB::table('password_reset_tokens')->where('realm', $uid)->delete();
        });
    }
}

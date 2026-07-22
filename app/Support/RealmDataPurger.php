<?php

namespace App\Support;

use App\Ldap\Group;
use App\Models\GroupMailmanList;
use App\Models\GroupMembership;
use App\Models\PassportClient;
use App\Models\ProfilePicture;
use App\Models\RealmBranding;
use App\Models\RealmIdentityProvider;
use App\Models\RoleMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

            foreach (ProfilePicture::where('realm', $uid)->get() as $picture) {
                Storage::disk('public')->delete('avatars/'.$picture->file_id.'.webp');
                $picture->delete();
            }

            if ($branding = RealmBranding::forRealm($uid)) {
                foreach (['logo_id', 'background_id'] as $column) {
                    if ($branding->{$column}) {
                        Storage::disk('public')->delete('realm-branding/'.$branding->{$column});
                    }
                }
                $branding->delete();
            }

            // Child identity_provider_role_mappings rows cascade via their own
            // FK (see 2026_07_20_000007_create_sso_provider_role_mappings_table,
            // renamed by 2026_07_22_000002_rename_sso_provider_tables_to_identity_provider).
            RealmIdentityProvider::where('realm', $uid)->delete();

            // Same manual authCodes()/tokens() cleanup ListOidcClients::deleteCommit()
            // already does per-client - oauth_clients has no cascade FK to
            // its tokens, so a plain delete() would leave them dangling.
            foreach (PassportClient::where('community_uid', $uid)->get() as $client) {
                $client->authCodes()->delete();
                $client->tokens()->delete();
                $client->delete();
            }

            foreach (User::where('realm', $uid)->get() as $user) {
                // Not $user->tokens() - HasApiTokens::getProviderName()
                // requires an "eloquent" auth provider, but this app's
                // "users" provider is "ldap" and would throw.
                PassportToken::where('user_id', $user->id)->delete();
                $user->delete();
            }

            DB::table('password_reset_tokens')->where('realm', $uid)->delete();
        });
    }
}

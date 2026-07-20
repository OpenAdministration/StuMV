<?php

namespace App\Entities;

use App\Ldap\Group;
use App\Models\ProfilePicture;
use App\Models\User;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use OpenIDConnect\Claims\Traits\WithClaims;
use OpenIDConnect\Entities\Traits\WithCustomPermittedFor;
use OpenIDConnect\Interfaces\IdentityEntityInterface;

class IdentityEntity implements IdentityEntityInterface
{
    use EntityTrait;
    use WithClaims;
    use WithCustomPermittedFor;

    protected User $user;

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
        $this->user = User::findOrFail($identifier);

        // The local `user` table only holds full_name/email/username (synced
        // from LDAP on login) - the rest of the standard OIDC claims live
        // only in LDAP, same as the legacy SocialiteUser endpoint reads them.
        $ldapUser = $this->user->ldap();
        $picture = ProfilePicture::where('user', $this->user->username)->first();

        $this->setClaims([
            'name' => $this->user->full_name,
            'given_name' => $ldapUser->getFirstAttribute('givenName'),
            'family_name' => $ldapUser->getFirstAttribute('sn'),
            'preferred_username' => $this->user->username,
            'picture' => $picture ? asset('storage/avatars/'.$picture->file_id.'.webp') : null,

            'email' => $this->user->email,
            'email_verified' => $this->user->email_verified_at !== null,

            'phone_number' => $ldapUser->getFirstAttribute('telephoneNumber'),

            'address' => [
                'street_address' => $ldapUser->getFirstAttribute('street'),
                'postal_code' => $ldapUser->getFirstAttribute('postalCode'),
                'locality' => $ldapUser->getFirstAttribute('l'),
            ],

            // Only surfaced to clients granted the "groups" scope (see the
            // matching custom claim set in config/openid.php) - same source
            // as the Directory API's Users::groups().
            'groups' => Group::query()->in(Group::dnRoot($this->user->realm))
                ->where('uniqueMember', '=', $ldapUser->getDn())
                ->get()
                ->map(fn (Group $group): string => $group->getFirstAttribute('cn'))
                ->values()
                ->all(),
        ]);
    }
}

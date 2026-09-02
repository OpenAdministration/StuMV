<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Ldap\Community;
use App\Ldap\User as LdapUser;
use App\Models\UserAdditionalEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VerifyAdditionalEmailController extends Controller
{
    /**
     * Confirms an additional address and only then writes it to LDAP "mail",
     * which is what makes it count for identity-provider matching.
     */
    public function __invoke(Community $realm, UserAdditionalEmail $additionalEmail, string $hash): RedirectResponse
    {
        abort_unless($additionalEmail->realm === $realm->getShortCode(), 404);
        abort_unless(hash_equals(sha1($additionalEmail->address), $hash), 403);

        if ($additionalEmail->isVerified()) {
            return $this->done($realm, $additionalEmail, 'profile.emails_already_verified');
        }

        $ldapUser = LdapUser::query()
            ->in($realm->peopleDn())
            ->where('uid', '=', $additionalEmail->username)
            ->first() ?? abort(404);

        // Two accounts can both have it pending - whoever confirms first wins.
        $taken = LdapUser::query()->in($realm->peopleDn())->where('mail', '=', $additionalEmail->address)->first();

        if ($taken && $taken->getFirstAttribute('uid') !== $additionalEmail->username) {
            return $this->done($realm, $additionalEmail, 'profile.emails_error_taken');
        }

        DB::transaction(function () use ($additionalEmail, $ldapUser): void {
            $additionalEmail->forceFill(['verified_at' => now()])->save();

            $ldapUser->addAdditionalEmail($additionalEmail->address);
            $ldapUser->save();
        });

        return $this->done($realm, $additionalEmail, 'profile.emails_verified_success');
    }

    /** Someone confirming from their mail client is usually not signed in. */
    private function done(Community $realm, UserAdditionalEmail $additionalEmail, string $message): RedirectResponse
    {
        $isOwner = Auth::check()
            && Auth::user()->username === $additionalEmail->username
            && Auth::user()->realm === $additionalEmail->realm;

        $target = $isOwner
            ? to_route('profile', ['realm' => $realm->getShortCode(), 'username' => $additionalEmail->username])
            : to_route('realm.login', ['realm' => $realm->getShortCode()]);

        return $target->with('status', __($message, ['address' => $additionalEmail->address]));
    }
}

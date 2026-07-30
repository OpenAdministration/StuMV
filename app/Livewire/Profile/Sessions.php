<?php

namespace App\Livewire\Profile;

use App\Ldap\Community;
use App\Ldap\User;
use App\Models\User as EloquentUser;
use Flux\Flux;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Sessions extends Component
{
    #[Locked]
    public string $realm_uid;

    #[Locked]
    public $currentUsername;

    public function mount(Community $realm, $username): void
    {
        $this->authorize('manageProfile', [User::class, $realm, $username]);
        $this->realm_uid = $realm->getShortCode();
        $this->currentUsername = $username;
    }

    private function eloquentUser(): EloquentUser
    {
        return EloquentUser::where('username', $this->currentUsername)
            ->where('realm', $this->realm_uid)
            ->firstOrFail();
    }

    public function render()
    {
        $ldapUser = User::query()
            ->in(Community::findOrFailByUid($this->realm_uid)->peopleDn())
            ->where('uid', '=', $this->currentUsername)
            ->first() ?? abort(404);

        $sessions = DB::table('sessions')
            ->where('user_id', $this->eloquentUser()->id)
            ->orderByDesc('last_activity')
            ->get();

        return view('livewire.profile.sessions', [
            'sessions' => $sessions,
            'currentSessionId' => session()->getId(),
            'givenName' => $ldapUser->getFirstAttribute('givenName'),
            'sn' => $ldapUser->getFirstAttribute('sn'),
        ])->title(__('profile.sessions'));
    }

    /**
     * Ends one specific session, never the visitor's own current one - that
     * always goes through the normal logout flow instead (which also tears
     * down the auth guard and back-channel-notifies OIDC clients, not just
     * the session store - see App\Support\EndsAuthenticatedSession).
     */
    public function logoutSession(string $sessionId): void
    {
        $this->authorize('manageProfile', [User::class, Community::findOrFailByUid($this->realm_uid), $this->currentUsername]);

        abort_if($sessionId === session()->getId(), 403);

        // Scoped to this user's own sessions - without this, the session id
        // from the request could belong to an entirely different account.
        $owned = DB::table('sessions')
            ->where('id', $sessionId)
            ->where('user_id', $this->eloquentUser()->id)
            ->exists();

        abort_unless($owned, 404);

        resolve('session')->getHandler()->destroy($sessionId);

        Flux::toast(variant: 'success', text: __('profile.session_logged_out_success'));
    }

    /**
     * Ends every session for this user except the visitor's own current one.
     */
    public function logoutOtherSessions(): void
    {
        $this->authorize('manageProfile', [User::class, Community::findOrFailByUid($this->realm_uid), $this->currentUsername]);

        $otherSessionIds = DB::table('sessions')
            ->where('user_id', $this->eloquentUser()->id)
            ->where('id', '!=', session()->getId())
            ->pluck('id');

        foreach ($otherSessionIds as $sessionId) {
            resolve('session')->getHandler()->destroy($sessionId);
        }

        Flux::toast(variant: 'success', text: __('profile.other_sessions_logged_out_success'));
        $this->closeLogoutOtherSessionsModal();
    }

    public function closeLogoutOtherSessionsModal(): void
    {
        Flux::modal('logout-other-sessions')->close();
    }
}

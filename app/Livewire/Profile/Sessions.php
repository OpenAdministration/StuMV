<?php

namespace App\Livewire\Profile;

use App\Ldap\Community;
use App\Ldap\User;
use App\Models\User as EloquentUser;
use App\Support\UserAgentParser;
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

    public string $sortField = 'last_activity';

    public string $sortDirection = 'desc';

    public function mount(Community $realm, $username): void
    {
        $this->authorize('manageProfile', [User::class, $realm, $username]);
        $this->realm_uid = $realm->getShortCode();
        $this->currentUsername = $username;
    }

    public function sortBy($field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDirection = 'asc';
            $this->sortField = $field;
        }
    }

    private function eloquentUser(): EloquentUser
    {
        return EloquentUser::where('username', $this->currentUsername)
            ->where('realm', $this->realm_uid)
            ->firstOrFail();
    }

    public function render()
    {
        $peopleDn = Community::findOrFailByUid($this->realm_uid)->peopleDn();

        $ldapUser = User::query()
            ->in($peopleDn)
            ->where('uid', '=', $this->currentUsername)
            ->first() ?? abort(404);

        $lastLogin = User::lastSuccessfulLoginByUsername($this->currentUsername, $peopleDn);

        // The "device" column is displayed as a parsed description
        // (UserAgentParser), but sorted by the raw user_agent column it's
        // derived from - same grouping in practice, without needing to sort
        // the collection in PHP after the map() below.
        $sortColumn = $this->sortField === 'device' ? 'user_agent' : $this->sortField;

        $sessions = DB::table('sessions')
            ->where('user_id', $this->eloquentUser()->id)
            ->orderBy($sortColumn, $this->sortDirection)
            ->get()
            ->map(function ($session) {
                $session->device_description = UserAgentParser::describe($session->user_agent) ?? '—';

                return $session;
            });

        return view('livewire.profile.sessions', [
            'sessions' => $sessions,
            'currentSessionId' => session()->getId(),
            'givenName' => $ldapUser->getFirstAttribute('givenName'),
            'sn' => $ldapUser->getFirstAttribute('sn'),
            'lastLogin' => $lastLogin,
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

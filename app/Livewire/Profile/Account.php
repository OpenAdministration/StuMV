<?php

namespace App\Livewire\Profile;

use App\Ldap\Community;
use App\Ldap\User;
use App\Support\AccountPurger;
use App\Support\EndsAuthenticatedSession;
use Flux\Flux;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Account extends Component
{
    #[Locked]
    public string $realm_uid;

    #[Locked]
    public string $currentUsername;

    public bool $userIsActive = true;

    public string $deleteConfirmation = '';

    public function mount(Community $realm, string $username): void
    {
        $this->authorize('manageProfile', [User::class, $realm, $username]);
        $this->realm_uid = $realm->getShortCode();
        $this->currentUsername = $username;
        $this->userIsActive = ! $this->isLocked($this->findUserWithLockStatus());
    }

    public function render()
    {
        $user = $this->findUserWithLockStatus();

        return view('livewire.profile.account', [
            'givenName' => $user->getFirstAttribute('givenName'),
            'sn' => $user->getFirstAttribute('sn'),
            'canToggleActive' => auth()->user()->can('admin', $this->community()),
            'canDelete' => $this->canDelete(),
        ])->title(__('profile.account'));
    }

    public function updatedUserIsActive(): void
    {
        $this->authorize('admin', $this->community());

        $user = $this->findUserWithLockStatus();
        if ($this->userIsActive !== $user->hasAttribute('pwdAccountLockedTime')) {
            return;
        }

        if ($this->userIsActive) {
            $user->removeAttribute('pwdAccountLockedTime');
        } else {
            $user->setAttribute('pwdAccountLockedTime', '00000101000000Z');
        }
        $user->save();

        $user->recordAction($this->userIsActive ? 'member.unlocked' : 'member.locked', $this->realm_uid);
        Flux::toast(variant: 'success', text: __('common.saved'));
    }

    public function deleteAccount(EndsAuthenticatedSession $session)
    {
        abort_unless($this->canDelete(), 403);

        $this->validate(
            ['deleteConfirmation' => ['required', 'in:'.$this->currentUsername]],
            ['deleteConfirmation.in' => __('profile.delete_account_confirmation_mismatch')],
        );

        if ($this->isOwnAccount()) {
            $session->end();
            AccountPurger::purge($this->community(), $this->currentUsername);

            return $this->redirect(route('realm.login', ['realm' => $this->realm_uid]));
        }

        AccountPurger::purge($this->community(), $this->currentUsername);
        Flux::toast(variant: 'success', text: __('profile.delete_account_success'));

        return $this->redirect(route('realms.members', ['realm' => $this->realm_uid]), navigate: true);
    }

    private function canDelete(): bool
    {
        return $this->isOwnAccount() || auth()->user()->can('remove_member', $this->community());
    }

    private function isOwnAccount(): bool
    {
        $user = auth()->user();

        return $user->username === $this->currentUsername && $user->realm === $this->realm_uid;
    }

    private function community(): Community
    {
        return Community::findOrFailByUid($this->realm_uid);
    }

    private function isLocked(User $user): bool
    {
        return $user->getFirstAttribute('pwdAccountLockedTime') === '00000101000000Z';
    }

    /** pwdAccountLockedTime is operational, so it has to be selected explicitly. */
    private function findUserWithLockStatus(): User
    {
        return User::query()
            ->in($this->community()->peopleDn())
            ->select(['*', 'pwdAccountLockedTime'])
            ->where('uid', '=', $this->currentUsername)
            ->first() ?? abort(404);
    }
}

<div class="px-6 border-b border-zinc-200 dark:border-zinc-700">
    <flux:navbar class="-mb-1">
        <flux:navbar.item wire:navigate href="{{ route('profile', ['username' => $username]) }}">{{ __('Profile') }}</flux:navbar.item>
        <flux:navbar.item wire:navigate href="{{ route('profile.picture', ['username' => $username]) }}">{{ __('profile.picture') }}</flux:navbar.item>
        <flux:navbar.item wire:navigate href="{{ route('profile.memberships', ['username' => $username]) }}">{{ __('profile.memberships') }}</flux:navbar.item>
        <flux:navbar.item wire:navigate href="{{ route('password.change', ['username' => $username]) }}">{{ __('Change Password') }}</flux:navbar.item>
    </flux:navbar>
</div>
<flux:navbar class="px-6 -mt-6 lg:-mt-[5rem] -mx-6 lg:-mx-8 border-b border-zinc-200 dark:border-zinc-700 absolute z-1 w-full bg-zinc-50 dark:bg-zinc-800">
    <flux:navbar.item href="{{ route('profile', ['username' => $username]) }}">{{ __('Profile') }}</flux:navbar.item>
    <flux:navbar.item href="{{ route('profile.picture', ['username' => $username]) }}">{{ __('profile.picture') }}</flux:navbar.item>
    <flux:navbar.item href="{{ route('profile.memberships', ['username' => $username]) }}">{{ __('profile.memberships') }}</flux:navbar.item>
    <flux:navbar.item href="{{ route('password.change', ['username' => $username]) }}">{{ __('Change Password') }}</flux:navbar.item>
</flux:navbar>
<div class="border-b border-zinc-200 dark:border-zinc-700">
    <div class="mx-6 sm:mx-8">
        <div class="max-w-6xl mx-auto -mb-[1px] overflow-x-auto">
            <flux:navbar>
                <flux:navbar.item wire:navigate href="{{ route('profile', ['username' => $username]) }}">{{ __('profile.personal_data') }}</flux:navbar.item>
                <flux:navbar.item wire:navigate href="{{ route('profile.picture', ['username' => $username]) }}">{{ __('profile.picture') }}</flux:navbar.item>
                <flux:navbar.item wire:navigate href="{{ route('profile.memberships', ['username' => $username]) }}">{{ __('profile.memberships') }}</flux:navbar.item>
                <flux:navbar.item wire:navigate href="{{ route('password.change', ['username' => $username]) }}">{{ __('profile.change_password_title') }}</flux:navbar.item>
            </flux:navbar>
        </div>
    </div>
</div>
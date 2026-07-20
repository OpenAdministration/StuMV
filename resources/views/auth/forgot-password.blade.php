<x-guest-layout :branding="$branding">
    <x-auth-card>
        <!-- Session Status -->
        @if(session('status'))
            <div class="w-full max-w-[28rem]!">
                <x-auth-session-status :status="session('status')" />
            </div>
        @endif

        <form method="POST" action="{{ route('password.email', ['realm' => $realm->getShortCode()]) }}">
            @csrf
            <flux:card class="grid gap-6 w-full bg-zinc-50 dark:bg-zinc-800 sm:bg-white sm:dark-bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-sm">
                <x-auth-logo :branding="$branding" />

                <flux:heading size="xl">{{ __('auth.forgot_password') }}</flux:heading>

                <flux:text>{{ __('auth.forgot_password_text') }}</flux:text>

                <flux:field>
                    <flux:label>{{ __('common.email') }}</flux:label>
                    <flux:input name="mail" :value="old('email')" required />
                </flux:field>

                <div class="flex flex-col gap-2">
                    <flux:button variant="primary" icon="send" type="submit">
                        {{ __('auth.send_reset_link') }}
                    </flux:button>
                    <flux:button icon="ban" wire:navigate href="{{ route('realm.login', ['realm' => $realm->getShortCode()]) }}">{{  __('common.cancel') }}</flux:button>
                </div>
            </flux:card>
        </form>
    </x-auth-card>
</x-guest-layout>

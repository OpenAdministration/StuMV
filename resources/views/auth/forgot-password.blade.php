<x-guest-layout>
    <x-auth-card>
        <!-- Session Status -->
        @if(session('status'))
            <div class="w-full max-w-[28rem]!">
                <x-auth-session-status :status="session('status')" />
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <flux:card class="grid gap-6 w-full bg-zinc-50 dark:bg-zinc-900 sm:bg-white sm:dark-bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-xs">
                <flux:heading size="xl">{{ __('Forgot your password?') }}</flux:heading>

                <flux:text>{{ __('auth.forgot_password_text') }}</flux:text>

                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input name="mail" :value="old('email')" required />
                </flux:field>

                <div class="flex flex-wrap gap-3 items-center justify-end">
                    <flux:button icon="ban" href="{{ route('login') }}">{{  __('Cancel') }}</flux:button>
                    <flux:button variant="primary" icon="send" type="submit">
                        {{ __('Send Reset Link') }}
                    </flux:button>
                </div>
            </flux:card>
        </form>
    </x-auth-card>
</x-guest-layout>

<x-guest-layout>
    <x-auth-card>

        <form method="POST" action="{{ route('password.email') }}">
            @csrf
            <div class="grid gap-6 sm:w-[25rem]">
                <flux:heading size="xl">{{ __('Forgot your password?') }}</flux:heading>

                <flux:text>{{ __('auth.forgot_password_text') }}</flux:text>

                <!-- Session Status -->
                <x-auth-session-status :status="session('status')" />

                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input type="text" name="mail" :value="old('email')" required />
                </flux:field>

                <div class="flex gap-x-3 items-center justify-end">
                    <flux:button href="{{ route('login') }}">{{  __('Cancel') }}</flux:button>
                    <flux:button variant="primary" type="submit">
                        {{ __('Send Reset Link') }}
                    </flux:button>
                </div>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>

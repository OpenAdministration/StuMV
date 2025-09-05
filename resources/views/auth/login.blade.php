<x-guest-layout>
    <x-auth-card>
        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <!-- Validation Errors -->
        <flux:heading size="xl">{{ __('Login') }}</flux:heading>
        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="grid gap-6 w-full sm:w-[25rem]">
                <flux:field>
                    <flux:label>{{ __('Username or Mail') }}</flux:label>
                    <flux:input type="text" name="uid" id="uid" :value="old('uid')" required autofocus />
                    <flux:error name="uid" />
                </flux:field>

                <flux:field>
                    <div class="mb-3 flex justify-between">
                        <flux:label>{{ __('Password') }}</flux:label>
                        @if (Route::has('password.request'))
                            <flux:link wire:navigate href="{{ route('password.request') }}" variant="subtle" class="text-sm">{{ __('Forgot your password?') }}</flux:link>
                        @endif
                    </div>
                    <flux:input type="password" name="password" id="password" required />
                    <flux:error name="" />
                </flux:field>

                <flux:field variant="inline">
                    <flux:checkbox name="remember" />
                    <flux:label>{{ __('Remember me') }}</flux:label>
                </flux:field>

                <flux:button variant="primary" type="submit">{{ __('Log in') }}</flux:button>

                <flux:separator />

                <flux:button href="{{ route('register') }}">{{ __('Sign up and get started!') }}</flux:button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>

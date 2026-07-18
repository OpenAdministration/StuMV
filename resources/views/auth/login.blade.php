<x-guest-layout>
    <x-auth-card>
        <!-- Session Status -->
        @if(session('status'))
            <div class="w-full max-w-[28rem]!">
                <x-auth-session-status :status="session('status')" />
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="w-full flex">
            @csrf
            <flux:card class="grid gap-6 w-full bg-zinc-50 dark:bg-zinc-900 sm:bg-white sm:dark-bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-xs">
                <flux:field>
                    <flux:label>{{ __('auth.username_or_mail') }}</flux:label>
                    <flux:input type="text" name="uid" id="uid" :value="old('uid')" required autofocus tabindex="1" />
                    <flux:error name="uid" />
                </flux:field>

                <flux:field>
                    <div class="flex justify-between">
                        <flux:label>{{ __('common.password') }}</flux:label>
                        @if (Route::has('password.request'))
                            <flux:link wire:navigate href="{{ route('password.request') }}" variant="subtle" class="text-sm">{{ __('auth.forgot_password') }}</flux:link>
                        @endif
                    </div>
                    <flux:input type="password" name="password" id="password" required tabindex="2" />
                    <flux:error name="" />
                </flux:field>

                <flux:field variant="inline" class="items-center">
                    <flux:switch name="remember" />
                    <flux:label class="mb-0!">{{ __('auth.remember_me') }}</flux:label>
                </flux:field>

                <flux:button variant="primary" icon="log-in" type="submit" tabindex="3">{{ __('auth.log_in') }}</flux:button>

                <flux:separator />

                <flux:button icon="user-plus" href="{{ route('register') }}">{{ __('auth.sign_up_prompt') }}</flux:button>
            </flux:card>
        </form>
    </x-auth-card>
</x-guest-layout>

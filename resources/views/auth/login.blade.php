<x-guest-layout :branding="$branding">
    <x-auth-card>
        <!-- Session Status -->
        @if(session('status'))
            <div class="w-full max-w-[28rem]!">
                <x-auth-session-status :status="session('status')" />
            </div>
        @endif

        <form method="POST" action="{{ route('realm.login', ['realm' => $realm->getShortCode()]) }}" class="w-full flex">
            @csrf
                <flux:card class="p-0 w-full bg-zinc-50 dark:bg-zinc-900 sm:bg-white sm:dark:bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                <div class="p-6">
                    <x-auth-logo :branding="$branding" />
                </div>

                <div class="p-6 flex flex-col space-y-4">
                    <flux:field>
                        <flux:label>{{ __('auth.username_or_mail') }}</flux:label>
                        <flux:input type="text" name="uid" id="uid" :value="old('uid')" required autofocus />
                        <flux:error name="uid" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('common.password') }}</flux:label>
                        <flux:input type="password" name="password" id="password" required />
                        <flux:error name="password" />
                    </flux:field>

                    <flux:field variant="inline" class="items-center">
                        <flux:checkbox name="remember" />
                        <flux:label class="mb-0!">{{ __('auth.remember_me') }}</flux:label>
                    </flux:field>

                    <flux:button variant="primary" icon="log-in" type="submit">{{ __('auth.log_in') }}</flux:button>

                    @if($identityProviders->isNotEmpty())
                        <flux:separator text="{{ __('auth.or_log_in_with') }}" />

                        <div class="flex flex-col gap-2">
                            @foreach($identityProviders as $identityProvider)
                                <flux:button href="{{ route('identity-provider.redirect', ['realm' => $realm->getShortCode(), 'provider' => $identityProvider->id]) }}">
                                    {{ __('identity_providers.login_button', ['name' => $identityProvider->name]) }}
                                </flux:button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="p-6 grid grid-cols-2 gap-2">
                    <flux:button wire:navigate href="{{ route('password.request', ['realm' => $realm->getShortCode()]) }}">{{ __('auth.forgot_password') }}</flux:button>
                    <flux:button wire:navigate href="{{ route('realm.register', ['realm' => $realm->getShortCode()]) }}">{{ __('auth.sign_up_prompt') }}</flux:button>
                </div>
            </flux:card>
        </form>
    </x-auth-card>
</x-guest-layout>

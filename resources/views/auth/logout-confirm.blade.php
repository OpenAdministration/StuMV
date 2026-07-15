<x-guest-layout>
    <x-auth-card>
        <!-- Validation Errors -->
        @if(session('errors'))
            <div class="w-full max-w-[28rem]!">
                <x-auth-validation-errors :errors="$errors" />
            </div>
        @endif

        <flux:card class="grid gap-6 w-full bg-zinc-50 dark:bg-zinc-900 sm:bg-white sm:dark-bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-xs">
            <flux:heading size="xl">{{ __('Confirm logout') }}</flux:heading>
            <p>
                {{ __('auth.logout_confirmation', ['user' => $shown_username]) }}
            </p>
            <form method="POST" action="{{ route('logout', ['redirect_uri' => $redirect_uri]) }}">
                @csrf
                <div class="flex justify-evenly">
                    <flux:button icon="ban" href="/">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="primary" icon="log-out" type="submit">{{ __('Log Out') }}</flux:button>
                </div>
            </form>
        </flux:card>
    </x-auth-card>
</x-guest-layout>

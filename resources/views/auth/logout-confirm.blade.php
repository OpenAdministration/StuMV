<x-guest-layout :branding="$branding ?? null">
    <x-auth-card>
        <!-- Validation Errors -->
        @if(session('errors'))
            <div class="w-full max-w-[28rem]!">
                <x-auth-validation-errors :errors="$errors" />
            </div>
        @endif

        <flux:card class="grid gap-6 w-full bg-zinc-50 dark:bg-zinc-800 sm:bg-white sm:dark-bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-sm">
            <x-auth-logo :branding="$branding ?? null" />

            <flux:heading size="xl">{{ __('auth.confirm_logout_title') }}</flux:heading>
            <p>
                {{ __('auth.logout_confirmation', ['user' => $shown_username]) }}
            </p>
            <form method="POST" action="{{ $realm ? route('realm.logout', ['realm' => $realm->getShortCode(), 'redirect_uri' => $redirect_uri]) : route('logout', ['redirect_uri' => $redirect_uri]) }}">
                @csrf
                <div class="flex flex-wrap gap-3 items-center justify-end">
                    <flux:button icon="ban" href="/">{{ __('common.cancel') }}</flux:button>
                    <flux:button variant="primary" icon="log-out" type="submit">{{ __('auth.log_out_button') }}</flux:button>
                </div>
            </form>
        </flux:card>
    </x-auth-card>
</x-guest-layout>

<x-guest-layout>
    <x-auth-card>
        <x-slot:slot class="space-y-5">
            <h2 class="font-bold text-gray-900 sm:truncate sm:tracking-tight">{{ __('Confirm logout') }}</h2>
            <p>
                {{ __('auth.logout_confirmation', ['user' => $shown_username]) }}
            </p>
            <form method="POST" action="{{ route('logout', ['redirect_uri' => $redirect_uri]) }}">
                @csrf
                <div class="flex justify-evenly">
                    <flux:button icon="ban" href="/">{{ __('Cancel') }}</flux:button>
                    <flux:button variant="danger" icon="log-out" type="submit">{{ __('Log Out') }}</flux:button>
                </div>
            </form>
        </x-slot:slot>
        <!-- Validation Errors -->
    </x-auth-card>
</x-guest-layout>

<x-guest-layout :branding="$branding">
    <x-auth-card>
        @if(session('status') == 'verification-link-sent')
            <div class="w-full max-w-[28rem]!">
                <flux:callout variant="success" icon="circle-check" heading="{{ __('auth.verification_link_sent_text') }}" />
            </div>
        @endif

        <flux:card class="grid gap-6 w-full bg-zinc-50 dark:bg-zinc-800 sm:bg-white sm:dark-bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-sm">
            <x-auth-logo :branding="$branding" />

            <div>{{ __('auth.verification_text') }}</div>

            <div class="flex flex-wrap gap-2 items-center justify-end">
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <flux:button
                        type="submit"
                        icon="log-out"
                    >
                        {{ __('auth.log_out') }}
                    </flux:button>
                </form>
                <form method="POST" action="{{ route('verification.send', ['realm' => $realm->getShortCode()]) }}">
                    @csrf
                    <flux:button
                        variant="primary"
                        type="submit"
                        icon="send"
                    >
                        {{ __('auth.send_verification_email') }}
                    </flux:button>
                </form>
            </div>
        </flux:card>
    </x-auth-card>
</x-guest-layout>

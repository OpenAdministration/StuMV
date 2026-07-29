<x-guest-layout :branding="$branding">
    <x-auth-card>
        @if(session('status') == 'verification-link-sent')
            <div class="w-full max-w-[28rem]!">
                <flux:callout variant="success" icon="circle-check" heading="{{ __('auth.verification_link_sent_text') }}" />
            </div>
        @endif

        <flux:card class="p-0 w-full bg-white dark:bg-zinc-800 max-w-[28rem]! mx-auto border-1 shadow-sm divide-y divide-zinc-200 dark:divide-zinc-700">
            <div class="p-6">
                <x-auth-logo :branding="$branding" />
            </div>

            <div class="p-6">
                <div>{{ __('auth.verification_text') }}</div>
            </div>

            <div class="p-6 flex flex-wrap gap-2 items-center justify-end">
                <form method="POST" action="{{ route('realm.logout', ['realm' => $realm->getShortCode()]) }}">
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

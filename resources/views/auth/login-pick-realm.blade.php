<x-guest-layout :branding="$branding ?? null">
    <x-auth-card>
        @if(session('status'))
            <div class="w-full max-w-[28rem]!">
                <x-auth-session-status :status="session('status')" />
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="w-full flex">
            @csrf
            <flux:card class="p-0 w-full bg-zinc-50 dark:bg-zinc-800 max-w-[28rem]! mx-auto border-1 shadow-sm divide-y divide-zinc-200 dark:divide-zinc-700">
                <div class="p-6">
                    <x-auth-logo :branding="$branding ?? null" />
                </div>

                <div class="p-6 flex flex-col space-y-4">
                    <flux:field>
                        <flux:label>{{ __('auth.pick_realm_label') }}</flux:label>
                        <flux:select
                            variant="listbox"
                            searchable
                            name="realm"
                            placeholder="{{ __('auth.pick_realm_placeholder') }}"
                        >
                            @foreach($realms as $realm)
                                <flux:select.option value="{{ $realm->getShortCode() }}">{{ $realm->getLongName() ?: $realm->getShortCode() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="realm" />
                    </flux:field>

                    <flux:button variant="primary" icon="arrow-right" type="submit">{{ __('auth.pick_realm_continue') }}</flux:button>
                </div>
            </flux:card>
        </form>
    </x-auth-card>
</x-guest-layout>

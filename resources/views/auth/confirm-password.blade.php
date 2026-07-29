<x-guest-layout :branding="$branding ?? null">
    <x-auth-card>
        <flux:card class="p-0 w-full bg-white dark:bg-zinc-800 max-w-[28rem]! mx-auto border-1 shadow-sm divide-y divide-zinc-200 dark:divide-zinc-700">
            <div class="p-6">
                <x-auth-logo :branding="$branding ?? null" />
            </div>

            <div class="p-6">
                {{ __('auth.secure_area_text') }}
            </div>

            <div class="p-6">
                <!-- Validation Errors -->
                <form method="POST" action="{{ route('password.confirm') }}">
                    @csrf
                    <!-- Password -->
                    <x-input.group id="password" type="password" required autocomplete="current-password">
                        <x-slot name="label">{{ __('common.password') }}</x-slot>
                    </x-input.group>

                    <div class="flex justify-end mt-4">
                        <x-button>
                            {{ __('auth.confirm') }}
                        </x-button>
                    </div>
                </form>
            </div>
        </flux:card>
    </x-auth-card>
</x-guest-layout>

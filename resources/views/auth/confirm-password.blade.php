<x-guest-layout :branding="$branding ?? null">
    <x-auth-card>
        <x-auth-logo :branding="$branding ?? null" />

        <div class="mb-4 text-sm text-gray-600">
            {{ __('auth.secure_area_text') }}
        </div>

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
    </x-auth-card>
</x-guest-layout>

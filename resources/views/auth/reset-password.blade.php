<x-guest-layout :branding="$branding">
    <x-auth-card>
        <!-- Validation Errors -->
        @if(session('errors'))
            <div class="w-full max-w-[28rem]!">
                <x-auth-validation-errors :errors="$errors" />
            </div>
        @endif

        <flux:card class="p-0 w-full bg-white dark:bg-zinc-800 max-w-[28rem]! mx-auto border-1 shadow-sm divide-y divide-zinc-200 dark:divide-zinc-700">
            <div class="p-6">
                <x-auth-logo :branding="$branding" />
            </div>

            <form method="POST" action="{{ route('password.update', ['realm' => $realm->getShortCode()]) }}" class="divide-y divide-zinc-200 dark:divide-zinc-700">
                @csrf

                <!-- Password Reset Token -->
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <input type="hidden" name="mail" value="{{ $request->mail }}">

                <div class="p-6 flex flex-col gap-6">
                    <flux:heading size="xl">{{ __('auth.reset_password') }}</flux:heading>

                    <!-- Email Address -->
                    <flux:input id="mail" name="mail" :label="__('E-Mail')" :value="old('mail', $request->mail)" required disabled/>

                    <!-- Password -->
                    <flux:field>
                        <flux:label>{{ __('common.password') }}</flux:label>
                        <flux:input id="password" name="password" type="password" required autofocus />
                        <flux:error name="password" class="mb-2" />
                        <flux:description>{{ __('user.help.password') }}</flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('common.confirm_password') }}</flux:label>
                        <flux:input id="password_confirmation" name="password_confirmation" type="password" required />
                        <flux:error name="password_confirmation" />
                    </flux:field>
                </div>

                <div class="p-6 flex items-center justify-end">
                    <flux:button variant="primary" icon="rotate-ccw-key" type="submit">
                        {{ __('auth.reset_password') }}
                    </flux:button>
                </div>
            </form>
        </flux:card>
    </x-auth-card>
</x-guest-layout>

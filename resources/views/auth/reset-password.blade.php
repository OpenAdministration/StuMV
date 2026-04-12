<x-guest-layout>
    <x-auth-card>
        <!-- Validation Errors -->
        @if(session('errors'))
            <div class="w-full max-w-[28rem]!">
                <x-auth-validation-errors :errors="$errors" />
            </div>
        @endif

        <flux:card class="grid gap-6 w-full bg-zinc-50 dark:bg-zinc-900 sm:bg-white sm:dark-bg-zinc-800 max-w-[28rem]! mx-auto border-0 sm:border-1 sm:shadow-xs">
            <flux:heading size="xl">{{ __('Reset Password') }}</flux:heading>

            <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-6">
                @csrf

                <!-- Password Reset Token -->
                <input type="hidden" name="token" value="{{ $request->route('token') }}">
                <input type="hidden" name="mail" value="{{ $request->mail }}">

                <!-- Email Address -->
                <flux:input id="mail" name="mail" :label="__('E-Mail')" :value="old('mail', $request->mail)" required disabled/>

                <!-- Password -->
                <flux:field>
                    <flux:label>{{ __('Password') }}</flux:label>
                    <flux:input id="password" name="password" type="password" required autofocus />
                    <flux:error name="password" class="mb-2" />
                    <flux:description>{{ __('user.help.password') }}</flux:description>
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Confirm Password') }}</flux:label>
                    <flux:input id="password_confirmation" name="password_confirmation" type="password" required />
                    <flux:error name="password_confirmation" />
                </flux:field>

                <div class="flex items-center justify-end">
                    <flux:button variant="primary" icon="rotate-ccw-key" type="submit">
                        {{ __('Reset Password') }}
                    </flux:button>
                </div>
            </form>
        </flux:card>
    </x-auth-card>
</x-guest-layout>

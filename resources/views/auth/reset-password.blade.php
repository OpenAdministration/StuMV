<x-guest-layout>
    <x-auth-card>

        <!-- Validation Errors -->
        <x-auth-validation-errors class="mb-4" :errors="$errors" />
        <h2 class="font-bold text-gray-900 sm:truncate sm:tracking-tight">{{ __('Reset Password') }}</h2>
        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">
            <input type="hidden" name="mail" value="{{ $request->mail }}">

            <!-- Email Address -->
            <x-input.group id="mail" name="mail" :label="__('E-Mail')" :value="old('mail', $request->mail)" required disabled/>

            <!-- Password -->
            <x-input.group id="password" name="password" :label="__('Password')" type="password" required autofocus/>
            <x-input.group id="password_confirmation" name="password_confirmation" :label="__('Confirm Password')" type="password" required/>

            <div class="flex items-center justify-end mt-6">
                <x-button.primary type="submit">
                    {{ __('Reset Password') }}
                </x-button.primary>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>

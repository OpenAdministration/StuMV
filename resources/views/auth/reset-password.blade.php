<x-guest-layout>
    <x-auth-card>
        <x-slot name="logo">
            <a href="/">
                <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
            </a>
        </x-slot>

        <!-- Validation Errors -->
        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ route('password.update') }}">
            @csrf

            <!-- Password Reset Token -->
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <!-- Email Address -->
            <x-input.group id="mail" name="mail" :label="__('E-Mail')" :value="old('mail', $request->mail)" required autofocus/>

            <!-- Password -->
            <x-input.group id="password" name="password" :label="__('Password')" type="password" required/>
            <x-input.group id="password_confirmation" name="password_confirmation" :label="__('Confirm Password')" type="password" required/>

            <div class="flex items-center justify-end mt-4">
                <x-button type="submit">
                    {{ __('Reset Password') }}
                </x-button>
            </div>
        </form>
    </x-auth-card>
</x-guest-layout>
